<?php namespace Logingrupa\DashboardShopaholic\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * Add price_includes_vat to Shopaholic price types. Drives the Profit metric:
 * a cost price type storing gross prices gets VAT stripped in the profit math.
 */
class AddPriceTypesVatFlag extends Migration
{
    public const TABLE = 'lovata_shopaholic_price_types';
    public const COLUMN = 'price_includes_vat';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $obTable): void {
            $obTable->boolean(self::COLUMN)->default(false);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $obTable): void {
            $obTable->dropColumn(self::COLUMN);
        });
    }
}
