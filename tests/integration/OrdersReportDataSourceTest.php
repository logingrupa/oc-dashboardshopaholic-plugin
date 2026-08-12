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

    public function testAvgOrderValueTotalsOverRange(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 100.00);
        $this->seedOrderWithStat(['status_id' => 8, 'created_at' => '2026-08-02 10:00:00'], 50.00);
        $this->seedOrderWithStat(['status_id' => 4, 'created_at' => '2026-08-03 10:00:00'], 30.00);
        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-07-01 10:00:00'], 999.00);

        $arTotalMap = $this->fetchMetricTotals(['avg_order_value']);

        $this->assertEqualsWithDelta(60.0, (float) $arTotalMap['avg_order_value'], 0.001);
    }

    public function testCancelRateTotalsOverRange(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 100.00);
        $this->seedOrderWithStat(['status_id' => 8, 'created_at' => '2026-08-02 10:00:00'], 50.00);
        $this->seedOrderWithStat(['status_id' => 4, 'created_at' => '2026-08-03 10:00:00'], 30.00);
        // Canceled outside the range must not move the rate
        $this->seedOrderWithStat(['status_id' => 4, 'created_at' => '2026-07-01 10:00:00'], 10.00);

        $arTotalMap = $this->fetchMetricTotals(['cancel_rate']);

        // 1 canceled of 3 in range; Intl percent formatting scales client-side
        $this->assertEqualsWithDelta(1 / 3, (float) $arTotalMap['cancel_rate'], 0.001);
    }

    public function testMedianOddCount(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 10.00);
        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-02 10:00:00'], 500.00);
        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-03 10:00:00'], 20.00);

        $this->assertSame('20.00 EUR', $this->fetchMedianValue());
    }

    public function testMedianEvenCountAveragesMiddlePair(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 10.00);
        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-02 10:00:00'], 20.00);
        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-03 10:00:00'], 30.00);
        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-04 10:00:00'], 500.00);

        $this->assertSame('25.00 EUR', $this->fetchMedianValue());
    }

    public function testMedianRespectsDateRangeAndEmptyRangeIsZero(): void
    {
        $this->seedBaseData();

        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-07-01 10:00:00'], 999.00);
        $this->seedOrderWithStat(['status_id' => 1, 'created_at' => '2026-08-01 10:00:00'], 40.00);

        $this->assertSame('40.00 EUR', $this->fetchMedianValue());

        $obDataSource = new OrdersReportDataSource();
        $obData = $this->makeFetchData(
            OrdersReportDataSource::DIMENSION_MEDIAN_ORDER_VALUE,
            ['value', 'icon_status', 'link_enabled', 'link_href'],
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $arRowList = $obDataSource->getData($obData)->getRows();

        $this->assertSame('0.00 EUR', $arRowList[0]->oc_metric_value);
    }

    /**
     * Median card value for the August 2026 window.
     */
    private function fetchMedianValue(): string
    {
        $obDataSource = new OrdersReportDataSource();
        $obData = $this->makeFetchData(
            OrdersReportDataSource::DIMENSION_MEDIAN_ORDER_VALUE,
            ['value', 'icon_status', 'link_enabled', 'link_href'],
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31')
        );

        $arRowList = $obDataSource->getData($obData)->getRows();

        $this->assertCount(1, $arRowList);

        return $arRowList[0]->oc_metric_value;
    }

    /**
     * Metric totals for the August 2026 window, the way an indicator card in
     * metric-totals mode requests them.
     */
    private function fetchMetricTotals(array $arMetricCodeList): array
    {
        $obDataSource = new OrdersReportDataSource();
        $obData = $this->makeFetchData(
            'date',
            $arMetricCodeList,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31')
        );

        $obData->metricsConfiguration = [];
        foreach ($arMetricCodeList as $sMetricCode) {
            $obData->metricsConfiguration[$sMetricCode] = new Dashboard\Classes\ReportMetricConfiguration(true, false);
        }

        return $obDataSource->getData($obData)->getMetricTotals();
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
