<?php namespace Logingrupa\DashboardShopaholic\Classes\Helper;

use Backend;
use Carbon\Carbon;
use Db;
use SystemException;

/**
 * NewOrdersDataBuilder assembles the row data for the New Orders dashboard
 * widget. Kept out of the Vue widget class so the query is testable without
 * a backend controller.
 */
class NewOrdersDataBuilder
{
    public const ORDERS_TABLE = 'lovata_orders_shopaholic_orders';
    public const STATS_TABLE = 'logingrupa_dashboardshopaholic_order_stats';
    public const PAYMENT_METHODS_TABLE = 'lovata_orders_shopaholic_payment_methods';

    public const MIN_LIMIT = 1;
    public const MAX_LIMIT = 50;

    /**
     * @return array{orders: array, currency: string}
     */
    public static function build(int $iLimit, ?Carbon $obDateStart = null, ?Carbon $obDateEnd = null): array
    {
        if ($iLimit < self::MIN_LIMIT || $iLimit > self::MAX_LIMIT) {
            throw new SystemException('New orders limit must be between '.self::MIN_LIMIT.' and '.self::MAX_LIMIT);
        }

        $sCurrencyCode = ActiveCurrency::code();
        $arStatusIDList = StatusBuckets::getStatusIds(StatusBuckets::UNPROCESSED);

        $obQuery = Db::table(self::ORDERS_TABLE)
            ->leftJoin(self::STATS_TABLE, self::STATS_TABLE.'.order_id', '=', self::ORDERS_TABLE.'.id')
            ->leftJoin(self::PAYMENT_METHODS_TABLE, self::PAYMENT_METHODS_TABLE.'.id', '=', self::ORDERS_TABLE.'.payment_method_id')
            ->select([
                self::ORDERS_TABLE.'.id',
                self::ORDERS_TABLE.'.order_number',
                self::ORDERS_TABLE.'.created_at',
                self::ORDERS_TABLE.'.property',
                self::STATS_TABLE.'.total_price',
                self::PAYMENT_METHODS_TABLE.'.name as payment_method_name',
            ])
            ->orderByDesc(self::ORDERS_TABLE.'.created_at')
            ->orderByDesc(self::ORDERS_TABLE.'.id')
            ->limit($iLimit);

        if (!empty($arStatusIDList)) {
            $obQuery->whereIn(self::ORDERS_TABLE.'.status_id', $arStatusIDList);
        }

        // Follow the dashboard interval selector when a range is given
        if ($obDateStart !== null && $obDateEnd !== null) {
            $obQuery->whereBetween(self::ORDERS_TABLE.'.created_at', [
                $obDateStart->copy()->startOfDay()->toDateTimeString(),
                $obDateEnd->copy()->endOfDay()->toDateTimeString(),
            ]);
        }

        $arOrderList = [];
        foreach ($obQuery->get() as $obRow) {
            $arOrderList[] = self::makeOrderRow($obRow, $sCurrencyCode);
        }

        return [
            'orders' => $arOrderList,
            'currency' => $sCurrencyCode,
        ];
    }

    private static function makeOrderRow(object $obRow, string $sCurrencyCode): array
    {
        $arProperty = json_decode((string) $obRow->property, true) ?: [];

        $sCustomerName = trim(($arProperty['name'] ?? '').' '.($arProperty['last_name'] ?? ''));

        return [
            'id' => (int) $obRow->id,
            'order_number' => (string) $obRow->order_number,
            'created_at' => Carbon::parse($obRow->created_at)->format('d.m.Y H:i'),
            'customer_name' => $sCustomerName !== '' ? $sCustomerName : '-',
            'email' => (string) ($arProperty['email'] ?? ''),
            'total' => $obRow->total_price === null
                ? '-'
                : number_format((float) $obRow->total_price, 2, '.', ' ').' '.$sCurrencyCode,
            'payment_method' => (string) ($obRow->payment_method_name ?? ''),
            'url' => Backend::url('lovata/ordersshopaholic/orders/update/'.(int) $obRow->id),
        ];
    }
}
