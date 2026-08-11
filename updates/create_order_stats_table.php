<?php namespace Logingrupa\DashboardShopaholic\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * Reporting fact table: one row per order with promo-processed totals.
 */
class CreateOrderStatsTable extends Migration
{
    public const TABLE = 'logingrupa_dashboardshopaholic_order_stats';

    public function up()
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $obTable) {
            $obTable->engine = 'InnoDB';
            $obTable->increments('id');
            // explicit index names: the auto-generated ones exceed MySQL's 64 char limit
            $obTable->integer('order_id')->unsigned()->unique('lg_dsb_stats_order_id_unique');
            $obTable->dateTime('ordered_at')->index('lg_dsb_stats_ordered_at_index');
            $obTable->integer('status_id')->unsigned()->nullable()->index('lg_dsb_stats_status_id_index');
            $obTable->integer('payment_method_id')->unsigned()->nullable()->index('lg_dsb_stats_payment_method_index');
            $obTable->decimal('total_price', 15, 2)->default(0);
            $obTable->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(self::TABLE);
    }
}
