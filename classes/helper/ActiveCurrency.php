<?php namespace Logingrupa\DashboardShopaholic\Classes\Helper;

use Db;

/**
 * ActiveCurrency resolves the shop currency code for metric formatting.
 * Each production server holds one local currency (EUR or NOK), so the
 * default currency of the Shopaholic currency table is authoritative.
 */
class ActiveCurrency
{
    public const CURRENCY_TABLE = 'lovata_shopaholic_currency';
    public const FALLBACK_CODE = 'EUR';

    public static function code(): string
    {
        $sCode = Db::table(self::CURRENCY_TABLE)->where('is_default', 1)->value('code')
            ?? Db::table(self::CURRENCY_TABLE)->where('active', 1)->orderBy('id')->value('code');

        return is_string($sCode) && $sCode !== '' ? $sCode : self::FALLBACK_CODE;
    }
}
