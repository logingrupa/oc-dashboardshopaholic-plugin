<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Support\Facades\DB as Db;
use Lovata\OrdersShopaholic\Classes\Processor\OrderProcessor;
use Lovata\OrdersShopaholic\Models\Order;
use Lovata\OrdersShopaholic\Models\OrderPosition;
use October\Rain\Support\Facades\Event;

/**
 * OrderStatHandler against real Order and OrderPosition model events. The
 * checkout tests replay OrderProcessor::create's sequence step by step.
 * A write is counted by the OrderStat::updateOrCreate lookup it always runs.
 */
class OrderStatHandlerTest extends BaseDashboardShopaholicTestCase
{
    private const STAT_TABLE = 'logingrupa_dashboardshopaholic_order_stats';

    /**
     * @var array<int, array{offer_id: int, price: float, quantity: int}>
     */
    private const LINE_LIST = [
        ['offer_id' => 1, 'price' => 10.50, 'quantity' => 2],
        ['offer_id' => 2, 'price' => 4.25, 'quantity' => 3],
    ];

    private int $iStatWriteCount = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->seedBaseData();
        $this->seedOffers();

        Db::listen(function (QueryExecuted $obQuery): void {
            if (preg_match('/^select \* from ["`]'.self::STAT_TABLE.'["`] where \(["`]order_id["`] = \?\) limit 1$/', $obQuery->sql) === 1) {
                $this->iStatWriteCount++;
            }
        });
    }

    public function testCheckoutWritesOnceAtCommitAndOnceOnThePostCommitSave(): void
    {
        $obOrder = $this->openCheckout();
        $this->addCheckoutLines($obOrder);
        $this->assertSame(0, $this->iStatWriteCount, 'no write inside the checkout transaction');

        Db::commit();
        $this->assertSame(1, $this->iStatWriteCount, 'one write at the outer commit');
        $this->assertCheckoutRow($obOrder);

        $obOrder->save();
        Event::fire(OrderProcessor::EVENT_ORDER_CREATED, $obOrder);

        $this->assertSame(2, $this->iStatWriteCount);
        $this->assertCheckoutRow($obOrder);
    }

    public function testRowSurvivesAThrowingOrderCreatedListener(): void
    {
        // priority above the plugin listeners, so no order created listener of ours runs;
        // only the October facade (events.priority) honours the priority argument
        Event::listen(OrderProcessor::EVENT_ORDER_CREATED, function (): void {
            throw new RuntimeException('mail transport down');
        }, 100);
        $obOrder = $this->openCheckout();
        $this->addCheckoutLines($obOrder);
        Db::commit();
        $obOrder->save();
        $iWriteCountBefore = $this->iStatWriteCount;

        try {
            Event::fire(OrderProcessor::EVENT_ORDER_CREATED, $obOrder);
            $this->fail('the order created listener should have thrown');
        } catch (RuntimeException $obException) {
            $this->assertSame('mail transport down', $obException->getMessage());
        }

        $this->assertSame($iWriteCountBefore, $this->iStatWriteCount, 'the event wrote nothing');
        $this->assertCheckoutRow($obOrder);
    }

    public function testRowSurvivesAThrowingAfterSaveListenerOnThePostCommitSave(): void
    {
        $obOrder = $this->openCheckout();
        $this->addCheckoutLines($obOrder);
        Db::commit();
        // priority above the stat listener, so the stat afterSave never runs
        $obOrder->bindEvent('model.afterSave', function (): void {
            throw new RuntimeException('after save listener failed');
        }, 100);

        try {
            $obOrder->save();
            $this->fail('the after save listener should have thrown');
        } catch (RuntimeException $obException) {
            $this->assertSame('after save listener failed', $obException->getMessage());
        }

        $this->assertSame(1, $this->iStatWriteCount, 'only the commit write ran');
        $this->assertCheckoutRow($obOrder);
    }

    public function testSavepointCommitInsideTheCheckoutDoesNotWrite(): void
    {
        $obOrder = $this->openCheckout();
        $this->addCheckoutLines($obOrder);

        Db::beginTransaction();
        Db::commit();
        $this->assertSame(1, Db::transactionLevel());
        $this->assertSame(0, $this->iStatWriteCount);

        Db::commit();
        $this->assertSame(1, $this->iStatWriteCount);
        $this->assertCheckoutRow($obOrder);
    }

    public function testWritesInsideTheCommittingStepStillWriteOnceAtCommit(): void
    {
        // storeextender records the order on the cart and clears it at TransactionCommitting
        $obOrder = $this->openCheckout();
        $this->addCheckoutLines($obOrder);
        Event::listen(TransactionCommitting::class, function () use ($obOrder): void {
            $obOrder->save();
        });

        Db::commit();

        $this->assertSame(1, $this->iStatWriteCount, 'one write at the outer commit');
        $this->assertCheckoutRow($obOrder);
    }

    public function testSavepointRollbackInsideTheCheckoutKeepsTheClaim(): void
    {
        $obOrder = $this->openCheckout();
        $this->addCheckoutLines($obOrder);

        Db::beginTransaction();
        Db::rollBack();
        $this->assertSame(1, Db::transactionLevel());
        $obOrder->save();
        $this->assertSame(0, $this->iStatWriteCount, 'a save inside the checkout still writes nothing');

        Db::commit();
        $this->assertSame(1, $this->iStatWriteCount, 'one write at the outer commit');
        $this->assertCheckoutRow($obOrder);
    }

    public function testBackendOrderAndPositionEditsWriteOnEverySave(): void
    {
        $obOrder = $this->createBackendOrder();
        $this->assertSame(1, $this->iStatWriteCount);
        $this->assertStatRow((int) $obOrder->id, 0, 0, 0.0);

        $obPosition = OrderPosition::create(['order_id' => $obOrder->id, 'item_id' => 1, 'item_type' => self::OFFER_TYPE, 'quantity' => 3]);
        $this->assertSame(2, $this->iStatWriteCount);
        $this->assertStatRow((int) $obOrder->id, 1, 3, 31.50);

        $obPosition->delete();
        $this->assertSame(3, $this->iStatWriteCount);
        $this->assertStatRow((int) $obOrder->id, 0, 0, 0.0);
    }

    public function testRollbackBeforeOrderCreateResetsThePendingCheckout(): void
    {
        $this->openCheckoutWithoutOrder();
        Db::rollBack();

        $obOrder = $this->createBackendOrder();

        $this->assertSame(1, $this->iStatWriteCount);
        $this->assertStatRow((int) $obOrder->id, 0, 0, 0.0);
    }

    public function testRollbackAfterOrderCreateReleasesTheClaim(): void
    {
        $obRolledBackOrder = $this->openCheckout();
        $this->addCheckoutLines($obRolledBackOrder);
        Db::rollBack();
        $this->assertSame(0, Db::table(self::STAT_TABLE)->count());

        $obOrder = $this->createBackendOrder();

        $this->assertSame(1, $this->iStatWriteCount);
        $this->assertStatRow((int) $obOrder->id, 0, 0, 0.0);
    }

    /**
     * OrderProcessor::create up to Order::create: transaction, before_create, order.
     */
    private function openCheckout(): Order
    {
        $this->openCheckoutWithoutOrder();

        return Order::create(['status_id' => 1, 'currency_id' => 1, 'payment_method_id' => 2, 'site_id' => 1]);
    }

    private function openCheckoutWithoutOrder(): void
    {
        Db::beginTransaction();
        $mHalt = Event::fire(OrderProcessor::EVENT_UPDATE_ORDER_BEFORE_CREATE, [['payment_method_id' => 2], null], true);
        $this->assertNotFalse($mHalt, 'no before_create listener may stop the checkout');
    }

    /**
     * createOrderPosition per line: OrderPosition::create, then order_position()->add().
     */
    private function addCheckoutLines(Order $obOrder): void
    {
        foreach (self::LINE_LIST as $arLine) {
            $obPosition = OrderPosition::create([
                'order_id' => $obOrder->id,
                'item_id' => $arLine['offer_id'],
                'item_type' => self::OFFER_TYPE,
                'quantity' => $arLine['quantity'],
            ]);
            $obOrder->order_position()->add($obPosition);
        }
    }

    private function createBackendOrder(): Order
    {
        $this->iStatWriteCount = 0;

        return Order::create(['status_id' => 1, 'currency_id' => 1, 'payment_method_id' => 2, 'site_id' => 1]);
    }

    private function seedOffers(): void
    {
        foreach (self::LINE_LIST as $arLine) {
            Db::table('lovata_shopaholic_offers')->insert(['id' => $arLine['offer_id'], 'active' => 1, 'name' => 'Offer '.$arLine['offer_id'], 'quantity' => 10]);
            // main price: no price type
            Db::table('lovata_shopaholic_prices')->insert([
                'item_id' => $arLine['offer_id'], 'item_type' => self::OFFER_TYPE, 'price' => $arLine['price'], 'price_type_id' => null,
            ]);
        }
    }

    private function assertCheckoutRow(Order $obOrder): void
    {
        $iItemsQuantity = 0;
        $fTotalPrice = 0.0;
        foreach (self::LINE_LIST as $arLine) {
            $iItemsQuantity += $arLine['quantity'];
            $fTotalPrice += $arLine['price'] * $arLine['quantity'];
        }

        $this->assertStatRow((int) $obOrder->id, count(self::LINE_LIST), $iItemsQuantity, $fTotalPrice);
    }

    private function assertStatRow(int $iOrderID, int $iPositionsCount, int $iItemsQuantity, float $fTotalPrice): void
    {
        $obRow = Db::table(self::STAT_TABLE)->where('order_id', $iOrderID)->first();

        $this->assertNotNull($obRow, 'stat row for order '.$iOrderID);
        $this->assertSame($iPositionsCount, (int) $obRow->positions_count);
        $this->assertSame($iItemsQuantity, (int) $obRow->items_quantity);
        $this->assertEqualsWithDelta($fTotalPrice, (float) $obRow->total_price, 0.001);
    }
}
