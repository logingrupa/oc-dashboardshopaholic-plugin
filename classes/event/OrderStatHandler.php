<?php namespace Logingrupa\DashboardShopaholic\Classes\Event;

use Throwable;
use Illuminate\Support\Facades\Log;
use Lovata\OrdersShopaholic\Models\Order;
use Lovata\OrdersShopaholic\Models\OrderPosition;
use Lovata\OrdersShopaholic\Classes\Processor\OrderProcessor;
use Logingrupa\DashboardShopaholic\Classes\Helper\OrderStatWriter;

/**
 * OrderStatHandler keeps the order stat table in sync with orders.
 *
 * Write triggers:
 * - shopaholic.order.created: order is complete (positions + promo mechanisms attached)
 * - Order afterSave: status changes and backend edits
 * - OrderPosition afterSave/afterDelete: backend position edits
 *
 * Known gap: editing promo mechanism rows directly in the DB does not retrigger
 * a write; run dashboardshopaholic:backfill after manual surgery.
 */
class OrderStatHandler
{
    /**
     * @param \October\Rain\Events\PriorityDispatcher $obEvent (untyped: October passes PriorityDispatcher, not Illuminate Dispatcher)
     */
    public function subscribe($obEvent): void
    {
        $obEvent->listen(OrderProcessor::EVENT_ORDER_CREATED, function ($obOrder): void {
            if ($obOrder instanceof Order) {
                $this->safeWrite($obOrder);
            }
        });

        Order::extend(function (Order $obOrder): void {
            $obOrder->bindEvent('model.afterSave', function () use ($obOrder): void {
                $this->safeWrite($obOrder);
            });

            $obOrder->bindEvent('model.afterDelete', function () use ($obOrder): void {
                $this->safeRemove((int) $obOrder->id);
            });
        });

        OrderPosition::extend(function (OrderPosition $obPosition): void {
            $obPosition->bindEvent('model.afterSave', function () use ($obPosition): void {
                $this->safeWriteParent($obPosition);
            });

            $obPosition->bindEvent('model.afterDelete', function () use ($obPosition): void {
                $this->safeWriteParent($obPosition);
            });
        });
    }

    private function safeWriteParent(OrderPosition $obPosition): void
    {
        $obOrder = Order::find($obPosition->order_id);
        if ($obOrder instanceof Order) {
            $this->safeWrite($obOrder);
        }
    }

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
