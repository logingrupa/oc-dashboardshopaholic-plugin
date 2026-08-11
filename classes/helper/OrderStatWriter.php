<?php namespace Logingrupa\DashboardShopaholic\Classes\Helper;

use Logingrupa\DashboardShopaholic\Models\OrderStat;
use Lovata\OrdersShopaholic\Classes\PromoMechanism\OrderPromoMechanismProcessor;
use Lovata\OrdersShopaholic\Models\Order;
use SystemException;

/**
 * OrderStatWriter computes and upserts the reporting row for one order.
 * Single write path shared by the event handler and the backfill command.
 */
class OrderStatWriter
{
    /**
     * Compute promo-processed totals and upsert the stat row.
     * @param bool $bReloadRelations pass false when the model was freshly loaded
     * with its relations (backfill) - reloading would discard the eager load
     */
    public static function write(Order $obOrder, bool $bReloadRelations = true): void
    {
        if (empty($obOrder->id)) {
            throw new SystemException('OrderStatWriter requires a persisted order');
        }

        // The processor caches per order id within one request. update() drops
        // that cache so totals include every position and mechanism written so far.
        if ($bReloadRelations) {
            $obOrder->reloadRelations();
        }
        $obProcessor = OrderPromoMechanismProcessor::update($obOrder);

        OrderStat::updateOrCreate(
            ['order_id' => (int) $obOrder->id],
            [
                'ordered_at' => $obOrder->created_at,
                'status_id' => self::intOrNull($obOrder->status_id),
                'payment_method_id' => self::intOrNull($obOrder->payment_method_id),
                'total_price' => (float) $obProcessor->getTotalPrice()->price_value,
            ]
        );
    }

    /**
     * Remove the stat row of a deleted order.
     */
    public static function remove(int $iOrderID): void
    {
        if ($iOrderID < 1) {
            throw new SystemException('OrderStatWriter::remove requires a positive order id');
        }

        OrderStat::where('order_id', $iOrderID)->delete();
    }

    private static function intOrNull(mixed $mValue): ?int
    {
        return is_numeric($mValue) && (int) $mValue > 0 ? (int) $mValue : null;
    }
}
