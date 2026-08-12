<?php namespace Logingrupa\DashboardShopaholic\Classes\Helper;

use Carbon\CarbonInterface;
use Db;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
    public const ORDERS_TABLE = 'lovata_orders_shopaholic_orders';

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

        $iItemsQuantity = 0;
        $iPositionsCount = 0;
        foreach ($obOrder->order_position as $obPosition) {
            $iPositionsCount++;
            $iItemsQuantity += (int) $obPosition->quantity;
        }

        $obOrderedAt = $obOrder->created_at;

        OrderStat::updateOrCreate(
            ['order_id' => (int) $obOrder->id],
            array_merge(
                [
                    'ordered_at' => $obOrderedAt,
                    'status_id' => self::intOrNull($obOrder->status_id),
                    'payment_method_id' => self::intOrNull($obOrder->payment_method_id),
                    'total_price' => (float) $obProcessor->getTotalPrice()->price_value,
                    'shipping_type_id' => self::intOrNull($obOrder->shipping_type_id),
                    'items_quantity' => $iItemsQuantity,
                    'positions_count' => $iPositionsCount,
                    'user_id' => self::intOrNull($obOrder->user_id),
                    'is_returning' => self::isReturningCustomer(
                        self::intOrNull($obOrder->user_id),
                        self::orderEmail($obOrder),
                        $obOrderedAt->toDateTimeString(),
                        (int) $obOrder->id
                    ),
                ],
                self::makeShippingTimeFields($obOrder, $obOrderedAt)
            )
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

    /**
     * A customer is returning when an earlier order exists with the same
     * user id, or with the same order email for guest checkouts. Earlier =
     * older created_at, id as the tie-break on equal timestamps.
     */
    public static function isReturningCustomer(?int $iUserID, ?string $sEmail, string $sOrderedAt, int $iOrderID): bool
    {
        if ($iUserID === null && $sEmail === null) {
            return false;
        }

        $obQuery = Db::table(self::ORDERS_TABLE)
            ->where(function (QueryBuilder $obEarlier) use ($sOrderedAt, $iOrderID): void {
                $obEarlier->where('created_at', '<', $sOrderedAt)
                    ->orWhere(function (QueryBuilder $obTieBreak) use ($sOrderedAt, $iOrderID): void {
                        $obTieBreak->where('created_at', $sOrderedAt)->where('id', '<', $iOrderID);
                    });
            });

        if ($iUserID !== null) {
            $obQuery->where('user_id', $iUserID);
        } else {
            // guest: match the checkout email inside the order property JSON
            $obQuery->whereRaw(self::makeEmailExpression().' = ?', [mb_strtolower($sEmail)]);
        }

        return $obQuery->exists();
    }

    private static function intOrNull(mixed $mValue): ?int
    {
        return is_numeric($mValue) && (int) $mValue > 0 ? (int) $mValue : null;
    }

    /**
     * Checkout email from the order property JSON, lowercased; null when absent.
     */
    private static function orderEmail(Order $obOrder): ?string
    {
        $sEmail = $obOrder->property['email'] ?? null;

        return is_string($sEmail) && trim($sEmail) !== '' ? mb_strtolower(trim($sEmail)) : null;
    }

    /**
     * SQLite json_extract returns unquoted text; MySQL needs json_unquote.
     * Lowercasing both sides makes the utf8mb4_bin JSON result compare
     * case-insensitively for ASCII emails.
     */
    private static function makeEmailExpression(): string
    {
        return Db::connection()->getDriverName() === 'sqlite'
            ? "lower(json_extract(property, '$.email'))"
            : "lower(json_unquote(json_extract(property, '$.email')))";
    }

    /**
     * Stamp shipped_at + hours_to_ship ONCE, when the status first sits in the
     * shipped bucket (sent/complete). A stamped row is never restamped, so
     * later saves and re-backfills keep the original shipping time. The order's
     * updated_at is the transition timestamp: exact for live status saves,
     * the last-touch approximation for historical rows during backfill.
     * The precomputed hours keep metric SQL free of date math (SQLite vs MySQL).
     * @return array<string, mixed>
     */
    private static function makeShippingTimeFields(Order $obOrder, CarbonInterface $obOrderedAt): array
    {
        $bAlreadyStamped = OrderStat::query()
            ->where('order_id', (int) $obOrder->id)
            ->whereNotNull('shipped_at')
            ->exists();
        if ($bAlreadyStamped) {
            return [];
        }

        $iStatusID = self::intOrNull($obOrder->status_id);
        if ($iStatusID === null || !in_array($iStatusID, StatusBuckets::getStatusIds(StatusBuckets::SHIPPED), true)) {
            return [];
        }

        $obShippedAt = $obOrder->updated_at ?? $obOrderedAt;
        $fHoursToShip = round(($obShippedAt->getTimestamp() - $obOrderedAt->getTimestamp()) / 3600, 2);

        return [
            'shipped_at' => $obShippedAt,
            'hours_to_ship' => max(0.0, $fHoursToShip),
        ];
    }
}
