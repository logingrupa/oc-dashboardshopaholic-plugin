<?php

use Illuminate\Support\Facades\DB as Db;
use Logingrupa\DashboardShopaholic\Classes\Helper\OrderStatWriter;

/**
 * Returning-customer detection against the orders table. The full write()
 * path needs the promo mechanism processor and is covered by the real-data
 * smoke check; the detection query is exercised here in isolation.
 */
class OrderStatWriterTest extends BaseDashboardShopaholicTestCase
{
    public function testUserWithEarlierOrderIsReturning(): void
    {
        $iFirstOrderID = $this->seedOrder(5, null, '2026-08-01 10:00:00');
        $iSecondOrderID = $this->seedOrder(5, null, '2026-08-05 10:00:00');
        $this->seedOrder(6, null, '2026-07-01 10:00:00');

        $this->assertFalse(OrderStatWriter::isReturningCustomer(5, null, '2026-08-01 10:00:00', $iFirstOrderID));
        $this->assertTrue(OrderStatWriter::isReturningCustomer(5, null, '2026-08-05 10:00:00', $iSecondOrderID));
    }

    public function testGuestMatchesEarlierOrderByEmailCaseInsensitive(): void
    {
        $this->seedOrder(null, 'Anna@Example.com', '2026-08-01 10:00:00');
        $iSecondOrderID = $this->seedOrder(null, 'anna@example.com', '2026-08-05 10:00:00');
        $iOtherOrderID = $this->seedOrder(null, 'other@example.com', '2026-08-06 10:00:00');

        $this->assertTrue(OrderStatWriter::isReturningCustomer(null, 'anna@example.com', '2026-08-05 10:00:00', $iSecondOrderID));
        $this->assertFalse(OrderStatWriter::isReturningCustomer(null, 'other@example.com', '2026-08-06 10:00:00', $iOtherOrderID));
    }

    public function testEqualTimestampsTieBreakOnOrderId(): void
    {
        $iFirstOrderID = $this->seedOrder(7, null, '2026-08-01 10:00:00');
        $iSecondOrderID = $this->seedOrder(7, null, '2026-08-01 10:00:00');

        $this->assertFalse(OrderStatWriter::isReturningCustomer(7, null, '2026-08-01 10:00:00', $iFirstOrderID));
        $this->assertTrue(OrderStatWriter::isReturningCustomer(7, null, '2026-08-01 10:00:00', $iSecondOrderID));
    }

    public function testNoUserAndNoEmailIsNeverReturning(): void
    {
        $this->seedOrder(null, 'someone@example.com', '2026-08-01 10:00:00');

        $this->assertFalse(OrderStatWriter::isReturningCustomer(null, null, '2026-08-05 10:00:00', 999));
    }

    private function seedOrder(?int $iUserID, ?string $sEmail, string $sCreatedAt): int
    {
        return (int) Db::table('lovata_orders_shopaholic_orders')->insertGetId([
            'status_id' => 1,
            'order_number' => 'W-'.mt_rand(1000, 9999),
            'user_id' => $iUserID,
            'property' => $sEmail === null ? null : json_encode(['email' => $sEmail]),
            'created_at' => $sCreatedAt,
            'updated_at' => $sCreatedAt,
        ]);
    }
}
