<?php namespace Logingrupa\DashboardShopaholic\Updates;

use Logingrupa\DashboardShopaholic\Classes\Helper\OrderIdIndex;
use October\Rain\Database\Updates\Migration;

/**
 * The upstream order promo mechanism table ships without an order_id index;
 * every promo processor load by order scanned the whole table.
 * Additive only - no upstream file is touched.
 */
class AddOrderPromoMechanismOrderIdIndex extends Migration
{
    public const TABLE = 'lovata_orders_shopaholic_order_promo_mechanism';
    public const INDEX = 'logingrupa_dsb_promo_mechanism_order_id';

    /**
     * Add the order_id index unless one already leads with order_id.
     */
    public function up()
    {
        OrderIdIndex::add(self::TABLE, self::INDEX);
    }

    /**
     * Drop the index this migration added.
     */
    public function down()
    {
        OrderIdIndex::drop(self::TABLE, self::INDEX);
    }
}
