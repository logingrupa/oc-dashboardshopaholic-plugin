<?php

use Logingrupa\DashboardShopaholic\Classes\Helper\NewOrdersDataBuilder;
use SystemException as CoreSystemException;

class NewOrdersDataBuilderTest extends BaseDashboardShopaholicTestCase
{
    public function testReturnsUnprocessedOrdersNewestFirstWithCustomerData(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat([
            'status_id' => 1,
            'order_number' => '260801-0001',
            'created_at' => '2026-08-01 10:00:00',
            'payment_method_id' => 1,
            'property' => json_encode(['name' => 'Linda', 'last_name' => 'Bieza', 'email' => 'linda@example.com']),
        ], 372.06);

        $this->seedOrderWithStat([
            'status_id' => 5,
            'order_number' => '260802-0001',
            'created_at' => '2026-08-02 10:00:00',
            'property' => json_encode(['name' => 'Dominiks', 'email' => 'dom@example.com']),
        ], 75.00);

        // Shipped order must not appear in the work queue
        $this->seedOrderWithStat([
            'status_id' => 8,
            'order_number' => '260803-0001',
            'created_at' => '2026-08-03 10:00:00',
        ], 999.00);

        $arResult = NewOrdersDataBuilder::build(10);

        $this->assertSame('EUR', $arResult['currency']);
        $this->assertCount(2, $arResult['orders']);

        $arNewest = $arResult['orders'][0];
        $this->assertSame('260802-0001', $arNewest['order_number']);
        $this->assertSame('Dominiks', $arNewest['customer_name']);
        $this->assertSame('75.00 EUR', $arNewest['total']);

        $arSecond = $arResult['orders'][1];
        $this->assertSame('Linda Bieza', $arSecond['customer_name']);
        $this->assertSame('linda@example.com', $arSecond['email']);
        $this->assertSame('372.06 EUR', $arSecond['total']);
        $this->assertSame('Vipps', $arSecond['payment_method']);
        $this->assertStringContainsString('lovata/ordersshopaholic/orders/update/', $arSecond['url']);
    }

    public function testLimitIsRespected(): void
    {
        $this->seedBaseData();

        foreach (range(1, 5) as $iIndex) {
            $this->seedOrderWithStat([
                'status_id' => 1,
                'created_at' => '2026-08-0'.$iIndex.' 10:00:00',
            ], 10.00);
        }

        $this->assertCount(3, NewOrdersDataBuilder::build(3)['orders']);
    }

    public function testMissingStatRowShowsDashTotal(): void
    {
        $this->seedBaseData();

        Illuminate\Support\Facades\DB::table('lovata_orders_shopaholic_orders')->insert([
            'status_id' => 1,
            'order_number' => 'NO-STAT',
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
        ]);

        $arResult = NewOrdersDataBuilder::build(10);

        $this->assertCount(1, $arResult['orders']);
        $this->assertSame('-', $arResult['orders'][0]['total']);
    }

    public function testDashboardIntervalFiltersOrders(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'order_number' => 'IN-RANGE', 'created_at' => '2026-08-05 10:00:00'], 10.00);
        $this->seedOrderWithStat(['status_id' => 1, 'order_number' => 'TOO-OLD', 'created_at' => '2026-07-01 10:00:00'], 20.00);

        $arResult = NewOrdersDataBuilder::build(
            10,
            Carbon\Carbon::parse('2026-08-01'),
            Carbon\Carbon::parse('2026-08-08')
        );

        $this->assertCount(1, $arResult['orders']);
        $this->assertSame('IN-RANGE', $arResult['orders'][0]['order_number']);
    }

    public function testOutOfRangeLimitFailsFast(): void
    {
        $this->expectException(CoreSystemException::class);

        NewOrdersDataBuilder::build(0);
    }
}
