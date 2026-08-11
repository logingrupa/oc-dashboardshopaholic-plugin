<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB as Db;
use Logingrupa\DashboardShopaholic\Classes\DataSource\OrdersReportDataSource;

/**
 * Exact-math integration tests for the orders data source.
 */
class OrdersReportDataSourceTest extends BaseDashboardShopaholicTestCase
{
    public function testTurnoverAndCountGroupByStatus(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 100.50);
        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-02 10:00:00'], 49.50);
        $this->seedOrderWithStat(['status_id' => 8, 'created_at' => '2026-08-03 10:00:00'], 200.00);
        $this->seedOrderWithStat(['status_id' => 4, 'created_at' => '2026-08-03 12:00:00'], 10.00);

        $arRowMap = $this->fetchRowsByDimension('status', ['orders_count', 'turnover']);

        $this->assertSame(2, (int) $arRowMap['Order received']->oc_metric_orders_count);
        $this->assertEqualsWithDelta(150.0, (float) $arRowMap['Order received']->oc_metric_turnover, 0.001);
        $this->assertSame(1, (int) $arRowMap['Sent']->oc_metric_orders_count);
        $this->assertEqualsWithDelta(200.0, (float) $arRowMap['Sent']->oc_metric_turnover, 0.001);
        $this->assertSame(1, (int) $arRowMap['Canceled']->oc_metric_orders_count);
        $this->assertArrayNotHasKey('Waiting for payment', $arRowMap);
    }

    public function testDateRangeExcludesOutsideOrders(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 100.00);
        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-07-01 10:00:00'], 999.00);

        $arRowMap = $this->fetchRowsByDimension('status', ['turnover']);

        $this->assertEqualsWithDelta(100.0, (float) $arRowMap['Order received']->oc_metric_turnover, 0.001);
    }

    public function testStatusDimensionJoinsStatusNames(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 8, 'created_at' => '2026-08-03 10:00:00'], 200.00);

        $arRowMap = $this->fetchRowsByDimension('status', ['orders_count']);

        $this->assertArrayHasKey('Sent', $arRowMap);
        $this->assertSame(1, (int) $arRowMap['Sent']->oc_metric_orders_count);
    }

    public function testPaymentMethodDimension(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'payment_method_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 60.00);
        $this->seedOrderWithStat(['status_id' => 1, 'payment_method_id' => 1, 'created_at' => '2026-08-02 10:00:00'], 40.00);
        $this->seedOrderWithStat(['status_id' => 1, 'payment_method_id' => 2, 'created_at' => '2026-08-02 11:00:00'], 15.00);

        $arRowMap = $this->fetchRowsByDimension('payment_method', ['turnover']);

        $this->assertEqualsWithDelta(100.0, (float) $arRowMap['Vipps']->oc_metric_turnover, 0.001);
        $this->assertEqualsWithDelta(15.0, (float) $arRowMap['Invoice']->oc_metric_turnover, 0.001);
    }

    public function testProfitMetricExactMathAndMissingCostRow(): void
    {
        $this->seedBaseData();
        $this->configureCostPriceType(3, false);

        $iOrderID = $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 302.50);

        // Position A: 121.00 gross at 21% VAT -> 100.00 net, cost 60.00 -> margin 40.00 x 2 = 80.00
        $this->seedPosition($iOrderID, 11, 121.00, 2, 21.0);
        $this->seedCostPrice(11, 3, 60.00);

        // Position B: no cost row -> contributes zero, never full revenue
        $this->seedPosition($iOrderID, 12, 60.50, 1, 21.0);

        $arRowMap = $this->fetchRowsByDimension('status', ['profit']);

        $this->assertEqualsWithDelta(80.0, (float) $arRowMap['Order received']->oc_metric_profit, 0.001);
    }

    public function testProfitStripsVatFromGrossCostPriceType(): void
    {
        $this->seedBaseData();
        $this->configureCostPriceType(3, true);

        $iOrderID = $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 121.00);

        // Gross 121.00 -> net 100.00; gross cost 72.60 -> net cost 60.00; margin 40.00
        $this->seedPosition($iOrderID, 11, 121.00, 1, 21.0);
        $this->seedCostPrice(11, 3, 72.60);

        $arRowMap = $this->fetchRowsByDimension('status', ['profit']);

        $this->assertEqualsWithDelta(40.0, (float) $arRowMap['Order received']->oc_metric_profit, 0.001);
    }

    public function testPerPriceTypeProfitMetricUsesOwnVatFlag(): void
    {
        $this->seedBaseData();
        // Gross cost type 3 - NOT the settings default (settings stay empty)
        Db::table('lovata_shopaholic_price_types')->insert([
            'id' => 3, 'active' => 1, 'name' => 'Vairum', 'code' => 'vairum', 'price_includes_vat' => 1,
        ]);

        $iOrderID = $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 121.00);

        // Gross 121.00 -> net 100.00; gross cost 72.60 -> net cost 60.00; margin 40.00
        $this->seedPosition($iOrderID, 11, 121.00, 1, 21.0);
        $this->seedCostPrice(11, 3, 72.60);

        $arRowMap = $this->fetchRowsByDimension('status', ['profit_3']);

        $this->assertEqualsWithDelta(40.0, (float) $arRowMap['Order received']->oc_metric_profit_3, 0.001);
    }

    public function testProfitIsZeroWithoutConfiguredCostPriceType(): void
    {
        $this->seedBaseData();

        $iOrderID = $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 121.00);
        $this->seedPosition($iOrderID, 11, 121.00, 1, 21.0);

        // Metric always registered - saved widgets referencing it never break
        $arRowMap = $this->fetchRowsByDimension('status', ['profit']);

        $this->assertEqualsWithDelta(0.0, (float) $arRowMap['Order received']->oc_metric_profit, 0.001);
    }

    /**
     * Seed a cost price type and point the plugin settings at it.
     */
    private function configureCostPriceType(int $iPriceTypeID, bool $bPriceIncludesVat): void
    {
        Db::table('lovata_shopaholic_price_types')->insert([
            'id' => $iPriceTypeID,
            'active' => 1,
            'name' => 'Vairum',
            'code' => 'vairum',
            'price_includes_vat' => $bPriceIncludesVat ? 1 : 0,
        ]);

        Logingrupa\DashboardShopaholic\Models\Settings::set('cost_price_type_id', $iPriceTypeID);
    }

    public function testBucketIndicatorCountsBacklogIgnoringDateRange(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2020-01-01 10:00:00'], 10.00);
        $this->seedOrderWithStat(['status_id' => 5, 'created_at' => '2026-08-01 10:00:00'], 20.00);
        $this->seedOrderWithStat(['status_id' => 8, 'created_at' => '2026-08-01 11:00:00'], 30.00);

        $obDataSource = new OrdersReportDataSource();
        $obData = $this->makeFetchData(
            'indicator@orders_unprocessed',
            ['value', 'icon_status', 'link_enabled', 'link_href'],
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31')
        );

        $arRowList = $obDataSource->getData($obData)->getRows();

        $this->assertCount(1, $arRowList);
        // Both unprocessed orders counted, the 2020 one included - it is a backlog counter
        $this->assertSame(2, (int) $arRowList[0]->oc_metric_value);
        $this->assertSame('important', $arRowList[0]->oc_metric_icon_status);
    }

    /**
     * Regression: the turnover chart 500ed because metric totals ran a query
     * ordered by oc_dimension - a column the totals select does not contain.
     * Reproduces the live widget: date dimension, oc_dimension sort, totals on.
     */
    public function testChartTotalsWithDimensionSortDoesNotFail(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 100.00);
        $this->seedOrderWithStat(['status_id' => 8, 'created_at' => '2026-08-02 10:00:00'], 50.00);

        $obDataSource = new OrdersReportDataSource();
        $obData = $this->makeFetchData(
            'date',
            ['turnover'],
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31')
        );
        $obData->orderRule = Dashboard\Classes\ReportDataOrderRule::createFromWidgetConfig('asc', 'oc_dimension');
        $obData->metricsConfiguration = [
            'turnover' => new Dashboard\Classes\ReportMetricConfiguration(true, false),
        ];

        $obResult = $obDataSource->getData($obData);

        // Date dimension pads every day of the range - 31 rows for August
        $this->assertCount(31, $obResult->getRows());
        $this->assertEqualsWithDelta(150.0, (float) $obResult->getMetricTotals()['turnover'], 0.001);
    }

    /**
     * Fetch rows for the August 2026 window keyed by dimension value.
     */
    private function fetchRowsByDimension(string $sDimensionCode, array $arMetricCodeList): array
    {
        $obDataSource = new OrdersReportDataSource();
        $obData = $this->makeFetchData(
            $sDimensionCode,
            $arMetricCodeList,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31')
        );

        $arRowMap = [];
        foreach ($obDataSource->getData($obData)->getRows() as $obRow) {
            $arRowMap[$obRow->oc_dimension] = $obRow;
        }

        return $arRowMap;
    }
}
