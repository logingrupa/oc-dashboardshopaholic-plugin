<?php namespace Logingrupa\DashboardShopaholic\VueComponents;

use Dashboard\Classes\ReportFetchData;
use Dashboard\Classes\VueReportWidgetBase;
use Logingrupa\DashboardShopaholic\Classes\Helper\NewOrdersDataBuilder;

/**
 * NewOrdersList shows the latest unprocessed orders with customer, total and
 * payment method, linking straight to the backend order page.
 */
class NewOrdersList extends VueReportWidgetBase
{
    /**
     * @var string componentName is the Vue component tag name.
     */
    protected $componentName = 'logingrupa-dashboardshopaholic-vuecomponents-neworderslist';

    /**
     * Latest unprocessed orders within the dashboard interval selection.
     */
    public function getData(ReportFetchData $obData): mixed
    {
        $arWidgetConfig = (array) post('widget_config');

        $iLimit = (int) ($arWidgetConfig['ordersLimit'] ?? 10);
        $iLimit = max(NewOrdersDataBuilder::MIN_LIMIT, min(NewOrdersDataBuilder::MAX_LIMIT, $iLimit));

        return NewOrdersDataBuilder::build($iLimit, $obData->dateStart, $obData->dateEnd);
    }
}
