<?php namespace Logingrupa\DashboardShopaholic\Models;

use Db;
use System\Models\SettingModel;

/**
 * Settings for the dashboard profit metric: which price type holds the cost
 * prices. Whether that price type stores prices with or without VAT lives on
 * the price type itself (Shopaholic > Price Types).
 *
 * @property int|null $cost_price_type_id
 */
class Settings extends SettingModel
{
    public $settingsCode = 'logingrupa_dashboardshopaholic_settings';

    public $settingsFields = 'fields.yaml';

    public function getCostPriceTypeIdOptions(): array
    {
        return Db::table('lovata_shopaholic_price_types')
            ->where('active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }
}
