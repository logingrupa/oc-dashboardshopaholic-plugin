<?php

use Logingrupa\DashboardShopaholic\Classes\Helper\StatusBuckets;

class StatusBucketsTest extends BaseDashboardShopaholicTestCase
{
    public function testBucketsMapStatusesByCode(): void
    {
        $this->seedBaseData();

        $this->assertSame([1, 5], StatusBuckets::getStatusIds(StatusBuckets::UNPROCESSED));
        $this->assertSame([3, 5, 8], StatusBuckets::getStatusIds(StatusBuckets::PAID));
        $this->assertSame([4], StatusBuckets::getStatusIds(StatusBuckets::CANCELED));
    }

    public function testOrdersAwaitingOnlinePaymentAreUnprocessed(): void
    {
        $this->seedBaseData();
        Db::table(StatusBuckets::STATUSES_TABLE)->insert(['id' => 9, 'name' => 'Awaiting online payment', 'code' => 'payment-pending']);

        $this->assertSame([1, 5, 9], StatusBuckets::getStatusIds(StatusBuckets::UNPROCESSED));
        $this->assertNotContains(9, StatusBuckets::getStatusIds(StatusBuckets::PAID));
    }

    public function testUnknownBucketFailsFast(): void
    {
        $this->expectException(SystemException::class);

        StatusBuckets::getStatusIds('nonsense');
    }
}
