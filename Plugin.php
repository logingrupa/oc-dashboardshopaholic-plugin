<?php namespace Logingrupa\DashboardShopaholic;

use Event;
use System\Classes\PluginBase;
use Dashboard\Classes\DashManager;
use Lovata\Shopaholic\Models\PriceType;
use Logingrupa\DashboardShopaholic\Classes\DataSource\OrdersReportDataSource;
use Logingrupa\DashboardShopaholic\Classes\Event\OrderStatHandler;
use Logingrupa\DashboardShopaholic\Console\BackfillOrderStats;
use Logingrupa\DashboardShopaholic\Models\Settings;
use Logingrupa\DashboardShopaholic\VueComponents\NewOrdersList;

/**
 * DashboardShopaholic plugin registration file.
 */
class Plugin extends PluginBase
{
    /**
     * @var array plugin dependencies
     */
    public $require = [
        'Lovata.Toolbox',
        'Lovata.Shopaholic',
        'Lovata.OrdersShopaholic',
    ];

    public function pluginDetails(): array
    {
        return [
            'name' => 'Dashboard for Shopaholic',
            'description' => 'Shop dashboard: order buckets, turnover, price-type profit metrics, new orders widget',
            'author' => 'Logingrupa',
            'icon' => 'icon-chart-bar',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('dashboardshopaholic.backfill', BackfillOrderStats::class);
    }

    public function boot(): void
    {
        Event::subscribe(OrderStatHandler::class);

        PriceType::extend(function (PriceType $obModel): void {
            $obModel->fillable[] = 'price_includes_vat';
        });

        if ($this->app->runningInBackend()) {
            DashManager::instance()->registerDataSourceClass(
                OrdersReportDataSource::class,
                'Shopaholic Orders'
            );

            $this->extendPriceTypeBackend();
        }
    }

    /**
     * VAT switch on the Shopaholic > Price Types form and list so the Profit
     * metric knows whether the cost price type stores gross or net prices.
     */
    protected function extendPriceTypeBackend(): void
    {
        Event::listen('backend.form.extendFields', function ($obWidget): void {
            if (!$obWidget->getController() instanceof \Lovata\Shopaholic\Controllers\PriceTypes
                || !$obWidget->model instanceof PriceType
                || $obWidget->isNested
            ) {
                return;
            }

            $obWidget->addFields([
                'price_includes_vat' => [
                    'label' => 'Prices include VAT (gross)',
                    'comment' => 'Off = prices stored without VAT (net). Used by the dashboard Profit metric when this price type is the cost basis',
                    'type' => 'switch',
                    'default' => 0,
                    'span' => 'left',
                ],
            ]);
        });

        Event::listen('backend.list.extendColumns', function ($obWidget): void {
            if (!$obWidget->getController() instanceof \Lovata\Shopaholic\Controllers\PriceTypes
                || !$obWidget->model instanceof PriceType
            ) {
                return;
            }

            $obWidget->addColumns([
                'price_includes_vat' => [
                    'label' => 'Incl VAT',
                    'type' => 'switch',
                ],
            ]);
        });
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label' => 'Dashboard for Shopaholic',
                'description' => 'Cost price type for the Profit metric',
                'category' => 'Shopaholic',
                'icon' => 'icon-chart-bar',
                'class' => Settings::class,
                'order' => 500,
                'permissions' => ['logingrupa.dashboardshopaholic.view_profit'],
            ],
        ];
    }

    public function registerReportWidgets(): array
    {
        return [
            NewOrdersList::class => [
                'label' => 'Shopaholic: New Orders',
                'group' => 'Shopaholic',
                'permissions' => ['logingrupa.dashboardshopaholic.access_dashboard'],
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'logingrupa.dashboardshopaholic.access_dashboard' => [
                'label' => 'Access the shop dashboard',
                'tab' => 'Dashboard for Shopaholic',
                'order' => 100,
            ],
            'logingrupa.dashboardshopaholic.view_profit' => [
                'label' => 'View profit metrics',
                'tab' => 'Dashboard for Shopaholic',
                'order' => 200,
            ],
        ];
    }
}
