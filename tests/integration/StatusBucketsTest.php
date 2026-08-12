<?php

use Logingrupa\DashboardShopaholic\Classes\Helper\StatusBuckets;

class StatusBucketsTest extends BaseDashboardShopaholicTestCase
{
    public function testBucketsMapStatusesByCode(): void
    {
        $this->seedBaseData();

        $this->assertSame([1, 5], StatusBuckets::getStatusIds(StatusBuckets::UNPROCESSED));
        $this->assertSame([4], StatusBuckets::getStatusIds(StatusBuckets::CANCELED));
    }

    public function testUnknownBucketFailsFast(): void
    {
        $this->expectException(SystemException::class);

        StatusBuckets::getStatusIds('nonsense');
    }
}
