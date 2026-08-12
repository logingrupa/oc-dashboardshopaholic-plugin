<?php namespace Logingrupa\DashboardShopaholic;

use Dashboard\Classes\DashManager;
use Event;
use Logingrupa\DashboardShopaholic\Classes\DataSource\OrdersReportDataSource;
use Logingrupa\DashboardShopaholic\Classes\Event\OrderStatHandler;
use Logingrupa\DashboardShopaholic\Classes\Helper\CardHelp;
use Logingrupa\DashboardShopaholic\Console\BackfillOrderStats;
use Logingrupa\DashboardShopaholic\Models\Settings;
use Logingrupa\DashboardShopaholic\VueComponents\NewOrdersList;
use Lovata\Shopaholic\Models\PriceType;
use System\Classes\PluginBase;

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
            'name' => 'logingrupa.dashboardshopaholic::lang.plugin.name',
            'description' => 'logingrupa.dashboardshopaholic::lang.plugin.description',
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
            $this->registerCardHelpAssets();
        }
    }

    /**
     * Help tooltips on the dashboard cards. The dashboard Vue widgets have no
     * help extension point, so a script decorates the rendered cards; the
     * translated payload rides in on the script tag's data attribute.
     */
    protected function registerCardHelpAssets(): void
    {
        Event::listen('backend.page.beforeDisplay', function ($obController): void {
            if (!$obController instanceof \Dashboard\Controllers\Index) {
                return;
            }

            $obController->addCss('/plugins/logingrupa/dashboardshopaholic/assets/css/card-help.css');
            $obController->addJs('/plugins/logingrupa/dashboardshopaholic/assets/js/card-help.js', [
                'id' => 'dashboardshopaholic-card-help',
                'data-help' => json_encode(CardHelp::makePayload(), JSON_UNESCAPED_UNICODE),
            ]);
        });
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
                    'label' => 'logingrupa.dashboardshopaholic::lang.field.price_includes_vat',
                    'comment' => 'logingrupa.dashboardshopaholic::lang.field.price_includes_vat_comment',
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
                    'label' => 'logingrupa.dashboardshopaholic::lang.field.price_includes_vat_column',
                    'type' => 'switch',
                ],
            ]);
        });
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label' => 'logingrupa.dashboardshopaholic::lang.settings.label',
                'description' => 'logingrupa.dashboardshopaholic::lang.settings.description',
                'category' => 'lovata.shopaholic::lang.tab.settings',
                'icon' => 'icon-chart-bar',
                'class' => Settings::class,
                'order' => 510,
                'permissions' => ['logingrupa.dashboardshopaholic.view_profit'],
            ],
        ];
    }

    public function registerReportWidgets(): array
    {
        return [
            NewOrdersList::class => [
                'label' => 'logingrupa.dashboardshopaholic::lang.widget.new_orders',
                'group' => 'Shopaholic',
                'permissions' => ['logingrupa.dashboardshopaholic.access_dashboard'],
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'logingrupa.dashboardshopaholic.access_dashboard' => [
                'label' => 'logingrupa.dashboardshopaholic::lang.permission.access_dashboard',
                'tab' => 'logingrupa.dashboardshopaholic::lang.permission.tab',
                'order' => 100,
            ],
            'logingrupa.dashboardshopaholic.view_profit' => [
                'label' => 'logingrupa.dashboardshopaholic::lang.permission.view_profit',
                'tab' => 'logingrupa.dashboardshopaholic::lang.permission.tab',
                'order' => 200,
            ],
        ];
    }
}
