<?php namespace Logingrupa\DashboardShopaholic\Updates;

use Dashboard\Models\Dashboard;
use Logingrupa\DashboardShopaholic\Classes\DataSource\OrdersReportDataSource;
use Logingrupa\DashboardShopaholic\VueComponents\NewOrdersList;
use October\Rain\Database\Updates\Migration;

/**
 * Seed the "Shopaholic Dashboard" on the MAIN backend dashboard with a default
 * layout. Creates the dashboard row if missing, fills the layout only when the
 * definition is empty - a user-customized layout is never overwritten.
 */
class SeedShopaholicDashboard extends Migration
{
    public const DASHBOARD_CODE = 'shopaholic-dash';
    public const OWNER_TYPE = \Dashboard\Controllers\Index::class;
    public const LANG = 'logingrupa.dashboardshopaholic::lang.';

    public function up(): void
    {
        $obDashboard = Dashboard::applyOwner(self::OWNER_TYPE)
            ->where('code', self::DASHBOARD_CODE)
            ->first();

        if ($obDashboard === null) {
            $obDashboard = new Dashboard();
            $obDashboard->owner_type = self::OWNER_TYPE;
            $obDashboard->code = self::DASHBOARD_CODE;
            $obDashboard->name = 'Shopaholic Dashboard';
            $obDashboard->icon = 'ph ph-shopping-cart';
            $obDashboard->is_global = true;
            $obDashboard->is_custom = true;
            $obDashboard->default_start = 'month';
            $obDashboard->default_end = 'today';
            $obDashboard->default_interval = 'day';
            $obDashboard->default_compare = 'prev-period';
        }

        // Never clobber a layout the user already saved
        if (empty($obDashboard->definition)) {
            $obDashboard->definition = $this->makeDefinition();
        }

        $obDashboard->forceSave();
    }

    public function down(): void
    {
        // Leave the dashboard in place - it may hold user customizations
    }

    /**
     * Rows in the saved-definition format the dashboard editor produces:
     * [{widgets: [{reportName, type, row, width, ..., configuration: {...}}]}]
     */
    private function makeDefinition(): array
    {
        return [
            ['widgets' => [
                $this->makeIndicator(1, 'orders_unprocessed', self::LANG.'bucket.unprocessed', 'ph ph-tray'),
                $this->makeIndicator(1, 'orders_processing', self::LANG.'bucket.processing', 'ph ph-clock'),
                $this->makeIndicator(1, 'orders_shipped', self::LANG.'bucket.shipped', 'ph ph-truck'),
                $this->makeIndicator(1, 'orders_canceled', self::LANG.'bucket.canceled', 'ph ph-x-circle'),
            ]],
            ['widgets' => [
                $this->makeNewOrdersWidget(2),
                $this->makeTurnoverChart(2),
            ]],
            ['widgets' => [
                $this->makeTable(3, 'by_status_table', self::LANG.'widget.by_status', 'status'),
                $this->makeTable(3, 'by_payment_table', self::LANG.'widget.by_payment', 'payment_method'),
            ]],
        ];
    }

    private function makeIndicator(int $iRow, string $sName, string $sTitle, string $sIcon): array
    {
        return $this->makeWidget([
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

    private function makeTurnoverChart(int $iRow): array
    {
        return $this->makeWidget([
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

    private function makeTable(int $iRow, string $sName, string $sTitle, string $sDimension): array
    {
        return $this->makeWidget([
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

    private function makeNewOrdersWidget(int $iRow): array
    {
        return $this->makeWidget([
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
     */
    private function makeWidget(array $arConfig, ?string $sWidgetClass = null): array
    {
        $arConfig['configuration'] = ['widgetClass' => $sWidgetClass] + $arConfig;

        return $arConfig;
    }
}
