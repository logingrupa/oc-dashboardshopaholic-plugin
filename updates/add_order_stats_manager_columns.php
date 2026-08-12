<?php namespace Logingrupa\DashboardShopaholic\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * Store-manager stat columns on the order stat table: shipping method,
 * basket size, returning-customer flag and time to ship. All computed by
 * OrderStatWriter; run dashboardshopaholic:backfill after migrating.
 */
class AddOrderStatsManagerColumns extends Migration
{
    public const TABLE = 'logingrupa_dashboardshopaholic_order_stats';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, 'shipping_type_id')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $obTable): void {
            // explicit index names: the auto-generated ones exceed MySQL's 64 char limit
            $obTable->integer('shipping_type_id')->unsigned()->nullable()->index('lg_dsb_stats_shipping_type_index');
            $obTable->integer('items_quantity')->unsigned()->default(0);
            $obTable->integer('positions_count')->unsigned()->default(0);
            $obTable->integer('user_id')->unsigned()->nullable()->index('lg_dsb_stats_user_id_index');
            $obTable->boolean('is_returning')->default(false);
            $obTable->dateTime('shipped_at')->nullable();
            $obTable->float('hours_to_ship')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'shipping_type_id')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $obTable): void {
            $obTable->dropColumn([
                'shipping_type_id',
                'items_quantity',
                'positions_count',
                'user_id',
                'is_returning',
                'shipped_at',
                'hours_to_ship',
            ]);
        });
    }
}
