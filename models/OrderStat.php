<?php namespace Logingrupa\DashboardShopaholic\Models;

use Model;

/**
 * OrderStat is a denormalized reporting row per order.
 *
 * Revenue on orders is NOT stored on the order record - it is computed by the
 * promo mechanism processor (discounts, free shipping, campaign prices).
 * This table persists the processor result once at write time so dashboard
 * queries stay exact and aggregate in plain SQL.
 */
class OrderStat extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'logingrupa_dashboardshopaholic_order_stats';

    public $rules = [
        'order_id' => 'required|integer|min:1',
        'ordered_at' => 'required',
    ];

    public $fillable = [
        'order_id',
        'ordered_at',
        'status_id',
        'payment_method_id',
        'total_price',
        'shipping_type_id',
        'items_quantity',
        'positions_count',
        'user_id',
        'is_returning',
        'shipped_at',
        'hours_to_ship',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'ordered_at' => 'datetime',
        'total_price' => 'float',
        'items_quantity' => 'integer',
        'positions_count' => 'integer',
        'is_returning' => 'boolean',
        'shipped_at' => 'datetime',
        'hours_to_ship' => 'float',
    ];
}
