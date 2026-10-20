<?php namespace Logingrupa\DashboardShopaholic\Classes\Event;

use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Log;
use Logingrupa\DashboardShopaholic\Classes\Helper\OrderStatWriter;
use Lovata\OrdersShopaholic\Classes\Processor\OrderProcessor;
use Lovata\OrdersShopaholic\Models\Order;
use Lovata\OrdersShopaholic\Models\OrderPosition;
use Throwable;

/**
 * OrderStatHandler keeps the order stat table in sync with orders.
 *
 * Write triggers:
 * - Checkout (OrderProcessor::create): the order created after
 *   shopaholic.order.before_create is claimed; its saves and its position
 *   saves inside the checkout transaction write nothing, the outer
 *   TransactionCommitted writes it once, then the post-commit save writes
 *   like any other save
 * - Order afterSave: status changes and backend edits
 * - OrderPosition afterSave/afterDelete: backend position edits
 * - TransactionRolledBack clears the pending flag and every claim
 *
 * Known gap: editing promo mechanism rows directly in the DB does not retrigger
 * a write; run dashboardshopaholic:backfill after manual surgery.
 */
class OrderStatHandler
{
    /**
     * Open between shopaholic.order.before_create and the next Order afterCreate.
     */
    private bool $bCheckoutPending = false;

    /**
     * Checkout orders waiting for the outer commit, keyed by order id.
     * @var array<int, Order>
     */
    private array $arClaimedOrderList = [];

    /**
     * @param \October\Rain\Events\PriorityDispatcher $obEvent (untyped: October passes PriorityDispatcher, not Illuminate Dispatcher)
     */
    public function subscribe($obEvent): void
    {
        // halt event: returning nothing lets the other before_create listeners run
        $obEvent->listen(OrderProcessor::EVENT_UPDATE_ORDER_BEFORE_CREATE, function (): void {
            $this->bCheckoutPending = true;
        });

        // connection events reach priority 0 listeners only
        $obEvent->listen(TransactionCommitted::class, function (TransactionCommitted $obCommitted): void {
            $this->writeClaimedOrders($obCommitted);
        });

        $obEvent->listen(TransactionRolledBack::class, function (): void {
            $this->reset();
        });

        Order::extend(function (Order $obOrder): void {
            $this->extendOrder($obOrder);
        });

        OrderPosition::extend(function (OrderPosition $obPosition): void {
            $this->extendOrderPosition($obPosition);
        });
    }

    /**
     * Bind the claim, save and delete listeners of one order instance.
     */
    private function extendOrder(Order $obOrder): void
    {
        $obOrder->bindEvent('model.afterCreate', function () use ($obOrder): void {
            $this->claim($obOrder);
        });

        $obOrder->bindEvent('model.afterSave', function () use ($obOrder): void {
            if (!$this->isClaimed((int) $obOrder->id)) {
                $this->safeWrite($obOrder);
            }
        });

        $obOrder->bindEvent('model.afterDelete', function () use ($obOrder): void {
            $this->safeRemove((int) $obOrder->id);
        });
    }

    /**
     * Bind the save and delete listeners of one order position instance.
     */
    private function extendOrderPosition(OrderPosition $obPosition): void
    {
        $obPosition->bindEvent('model.afterSave', function () use ($obPosition): void {
            $this->safeWriteParent($obPosition);
        });

        $obPosition->bindEvent('model.afterDelete', function () use ($obPosition): void {
            $this->safeWriteParent($obPosition);
        });
    }

    /**
     * Claim the order a checkout just created; any other new order stays per-save.
     */
    private function claim(Order $obOrder): void
    {
        if (!$this->bCheckoutPending) {
            return;
        }

        $this->bCheckoutPending = false;
        $this->arClaimedOrderList[(int) $obOrder->id] = $obOrder;
    }

    /**
     * True while the order waits for its checkout's outer commit.
     */
    private function isClaimed(int $iOrderID): bool
    {
        return isset($this->arClaimedOrderList[$iOrderID]);
    }

    /**
     * Write every claimed order once the outermost transaction committed.
     * Claims are released before writing, so later saves write as usual.
     */
    private function writeClaimedOrders(TransactionCommitted $obCommitted): void
    {
        if ($obCommitted->connection->transactionLevel() !== 0) {
            return;
        }

        foreach ($this->takeClaimedOrders() as $obOrder) {
            $this->safeWrite($obOrder);
        }
    }

    /**
     * Return the claimed orders and clear the flag and the claims.
     * @return array<int, Order>
     */
    private function takeClaimedOrders(): array
    {
        $arOrderList = $this->arClaimedOrderList;
        $this->reset();

        return $arOrderList;
    }

    /**
     * Close the checkout window and drop every claim.
     */
    private function reset(): void
    {
        $this->bCheckoutPending = false;
        $this->arClaimedOrderList = [];
    }

    /**
     * Rewrite the stat row of the position's order unless a checkout claimed it.
     */
    private function safeWriteParent(OrderPosition $obPosition): void
    {
        if ($this->isClaimed((int) $obPosition->order_id)) {
            return;
        }

        $obOrder = Order::find($obPosition->order_id);
        if ($obOrder instanceof Order) {
            $this->safeWrite($obOrder);
        }
    }

    /**
     * Write the stat row, logging instead of throwing.
     */
    private function safeWrite(Order $obOrder): void
    {
        try {
            OrderStatWriter::write($obOrder);
        } catch (Throwable $obException) {
            // boundary: a broken stat row must never abort checkout or a backend save
            Log::error('DashboardShopaholic order stat write failed: '.$obException->getMessage(), [
                'order_id' => $obOrder->id,
            ]);
        }
    }

    /**
     * Remove the stat row, logging instead of throwing.
     */
    private function safeRemove(int $iOrderID): void
    {
        try {
            OrderStatWriter::remove($iOrderID);
        } catch (Throwable $obException) {
            // boundary: same reason as safeWrite
            Log::error('DashboardShopaholic order stat remove failed: '.$obException->getMessage(), [
                'order_id' => $iOrderID,
            ]);
        }
    }
}
