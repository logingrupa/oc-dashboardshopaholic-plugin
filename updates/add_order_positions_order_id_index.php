<?php namespace Logingrupa\DashboardShopaholic\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * The upstream order positions table ships without an order_id index; the
 * profit metric runs a correlated subquery per order and needs one.
 * Additive only - no upstream file is touched.
 */
class AddOrderPositionsOrderIdIndex extends Migration
{
    const TABLE = 'lovata_orders_shopaholic_order_positions';
    const INDEX = 'logingrupa_dsb_positions_order_id';

    public function up()
    {
        if (!Schema::hasTable(self::TABLE) || $this->hasOrderIdIndex()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $obTable) {
            $obTable->index('order_id', self::INDEX);
        });
    }

    public function down()
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (Schema::getIndexes(self::TABLE) as $arIndex) {
            if (($arIndex['name'] ?? null) === self::INDEX) {
                Schema::table(self::TABLE, function (Blueprint $obTable) {
                    $obTable->dropIndex(self::INDEX);
                });
                return;
            }
        }
    }

    private function hasOrderIdIndex(): bool
    {
        foreach (Schema::getIndexes(self::TABLE) as $arIndex) {
            $arColumnList = $arIndex['columns'] ?? [];
            if (($arColumnList[0] ?? null) === 'order_id') {
                return true;
            }
        }

        return false;
    }
}
