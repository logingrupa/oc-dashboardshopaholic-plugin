<?php namespace Logingrupa\DashboardShopaholic\Classes\Helper;

use Logingrupa\DashboardShopaholic\Classes\DataSource\OrdersReportDataSource;
use Logingrupa\DashboardShopaholic\VueComponents\NewOrdersList;

/**
 * DefaultDashboardLayout builds the stock "Shopaholic Dashboard" definition in
 * the saved-definition format the dashboard editor produces:
 * [{widgets: [{reportName, type, row, width, ..., configuration: {...}}]}]
 *
 * Shared by the seed migration (fresh installs) and the period-cards update
 * migration (existing installs), which also needs the legacy layout to detect
 * an untouched stock dashboard.
 */
class DefaultDashboardLayout
{
    public const LANG = 'logingrupa.dashboardshopaholic::lang.';

    /**
     * @return array<int, array{widgets: array<int, array<string, mixed>>}>
     */
    public static function makeDefinition(): array
    {
        return [
            ['widgets' => [
                self::makeMetricIndicator(1, 'orders_period', self::LANG.'widget.orders_period', 'ph ph-shopping-cart', OrdersReportDataSource::METRIC_ORDERS_COUNT, 5),
                self::makeMetricIndicator(1, 'avg_order_value', self::LANG.'widget.avg_order_value', 'ph ph-coins', OrdersReportDataSource::METRIC_AVG_ORDER_VALUE, 5),
                self::makeMedianIndicator(1, 5),
                self::makeMetricIndicator(1, 'cancel_rate', self::LANG.'widget.cancel_rate', 'ph ph-x-circle', OrdersReportDataSource::METRIC_CANCEL_RATE, 5),
            ]],
            ['widgets' => [
                self::makeNewOrdersWidget(2),
                self::makeTurnoverChart(2),
            ]],
            ['widgets' => [
                self::makeTable(3, 'by_status_table', self::LANG.'widget.by_status', 'status'),
                self::makeTable(3, 'by_payment_table', self::LANG.'widget.by_payment', 'payment_method'),
            ]],
        ];
    }

    /**
     * The 1.1.0 stock layout, kept ONLY so the update migration can recognize a
     * dashboard nobody customized and swap it wholesale.
     *
     * @return array<int, array{widgets: array<int, array<string, mixed>>}>
     */
    public static function makeLegacyDefinition(): array
    {
        return [
            ['widgets' => [
                self::makeBucketIndicator(1, 'orders_unprocessed', self::LANG.'bucket.unprocessed', 'ph ph-tray'),
                self::makeBucketIndicator(1, 'orders_processing', self::LANG.'bucket.processing', 'ph ph-clock'),
                self::makeBucketIndicator(1, 'orders_shipped', self::LANG.'bucket.shipped', 'ph ph-truck'),
                self::makeBucketIndicator(1, 'orders_canceled', self::LANG.'bucket.canceled', 'ph ph-x-circle'),
            ]],
            ['widgets' => [
                self::makeNewOrdersWidget(2),
                self::makeTurnoverChart(2),
            ]],
            ['widgets' => [
                self::makeTable(3, 'by_status_table', self::LANG.'widget.by_status', 'status'),
                self::makeTable(3, 'by_payment_table', self::LANG.'widget.by_payment', 'payment_method'),
            ]],
        ];
    }

    /**
     * Backlog counter card driven by an indicator@ calculated dimension.
     *
     * @return array<string, mixed>
     */
    private static function makeBucketIndicator(int $iRow, string $sName, string $sTitle, string $sIcon): array
    {
        return self::makeWidget([
            'type' => 'indicator',
            'metrics' => [],
            'row' => $iRow,
            'width' => 5,
            'reportName' => $sName,
            'label' => null,
            'icon' => $sIcon,
            'title' => $sTitle,
            'linkText' => self::LANG.'widget.view_orders',
            'dimension' => 'indicator@'.$sName,
            'dataSource' => OrdersReportDataSource::class,
        ]);
    }

    /**
     * Metric-totals card: respects the dashboard date range and shows the
     * previous-period arrow when Compare Totals is on.
     *
     * @return array<string, mixed>
     */
    private static function makeMetricIndicator(int $iRow, string $sName, string $sTitle, string $sIcon, string $sMetric, int $iWidth): array
    {
        return self::makeWidget([
            'type' => 'indicator',
            'metrics' => [],
            'row' => $iRow,
            'width' => $iWidth,
            'reportName' => $sName,
            'label' => null,
            'icon' => $sIcon,
            'title' => $sTitle,
            'icon_status' => 'status-info',
            'metric' => $sMetric,
            'dimension' => 'date',
            'dataSource' => OrdersReportDataSource::class,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function makeMedianIndicator(int $iRow, int $iWidth): array
    {
        return self::makeWidget([
            'type' => 'indicator',
            'metrics' => [],
            'row' => $iRow,
            'width' => $iWidth,
            'reportName' => 'median_order_value',
            'label' => null,
            'icon' => 'ph ph-chart-bar',
            'title' => self::LANG.'widget.median_order_value',
            'dimension' => OrdersReportDataSource::DIMENSION_MEDIAN_ORDER_VALUE,
            'dataSource' => OrdersReportDataSource::class,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function makeTurnoverChart(int $iRow): array
    {
        return self::makeWidget([
            'type' => 'chart',
            'chartType' => 'line',
            'metrics' => [
                ['metric' => 'turnover', 'color' => '#84cc16', 'displayTotals' => true],
                ['metric' => 'profit', 'color' => '#0ea5e9', 'displayTotals' => true],
            ],
            'row' => $iRow,
            'width' => 10,
            'reportName' => 'turnover_chart',
            'label' => null,
            'title' => self::LANG.'widget.chart_title',
            'dimension' => 'date',
            'sortBy' => 'oc_dimension',
            'sortOrder' => 'asc',
            'dataSource' => OrdersReportDataSource::class,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function makeTable(int $iRow, string $sName, string $sTitle, string $sDimension): array
    {
        return self::makeWidget([
            'type' => 'table',
            'metrics' => [
                ['metric' => 'orders_count', 'color' => '#0ea5e9', 'displayTotals' => true],
                ['metric' => 'turnover', 'color' => '#84cc16', 'displayTotals' => true, 'displayRelativeBar' => true],
            ],
            'row' => $iRow,
            'width' => 10,
            'reportName' => $sName,
            'label' => null,
            'title' => $sTitle,
            'limit' => 10,
            'dimension' => $sDimension,
            'sortBy' => 'oc_metric-turnover',
            'sortOrder' => 'desc',
            'dataSource' => OrdersReportDataSource::class,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function makeNewOrdersWidget(int $iRow): array
    {
        return self::makeWidget([
            'type' => 'widget',
            'metrics' => [],
            'row' => $iRow,
            'width' => 10,
            'reportName' => 'new_orders_list',
            'label' => self::LANG.'widget.new_orders_title',
            'title' => self::LANG.'widget.new_orders_title',
            'ordersLimit' => 10,
            'componentName' => 'logingrupa-dashboardshopaholic-vuecomponents-neworderslist',
        ], NewOrdersList::class);
    }

    /**
     * Every saved widget carries its full config twice: top-level for the grid
     * and under `configuration` (plus widgetClass) for the report processor.
     *
     * @param array<string, mixed> $arConfig
     * @return array<string, mixed>
     */
    private static function makeWidget(array $arConfig, ?string $sWidgetClass = null): array
    {
        $arConfig['configuration'] = ['widgetClass' => $sWidgetClass] + $arConfig;

        return $arConfig;
    }
}
