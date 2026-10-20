<?php namespace Logingrupa\DashboardShopaholic\Updates;

use Logingrupa\DashboardShopaholic\Classes\Helper\OrderIdIndex;
use October\Rain\Database\Updates\Migration;

/**
 * The upstream order positions table ships without an order_id index; the
 * profit metric runs a correlated subquery per order and needs one.
 * Additive only - no upstream file is touched.
 */
class AddOrderPositionsOrderIdIndex extends Migration
{
    public const TABLE = 'lovata_orders_shopaholic_order_positions';
    public const INDEX = 'logingrupa_dsb_positions_order_id';

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
