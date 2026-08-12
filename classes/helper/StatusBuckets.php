<?php namespace Logingrupa\DashboardShopaholic\Classes\Helper;

use Db;
use SystemException;

/**
 * StatusBuckets groups order statuses into dashboard buckets by status CODE.
 * Convention over configuration: Shopaholic status codes already encode the
 * workflow (new*, in_progress, sent/complete, canceled) - no settings UI needed.
 */
class StatusBuckets
{
    public const UNPROCESSED = 'unprocessed';
    public const CANCELED = 'canceled';
    public const SHIPPED = 'shipped';

    public const BUCKET_LIST = [
        self::UNPROCESSED,
        self::CANCELED,
        self::SHIPPED,
    ];

    public const STATUS_CODE_MAP = [
        self::UNPROCESSED => ['new', 'new-payment-received', 'new-payment-error', 'new-payment-canceled'],
        self::CANCELED => ['canceled'],
        self::SHIPPED => ['sent', 'complete'],
    ];

    public const STATUSES_TABLE = 'lovata_orders_shopaholic_statuses';

    /**
     * @return int[] status IDs belonging to the bucket
     */
    public static function getStatusIds(string $sBucket): array
    {
        if (!in_array($sBucket, self::BUCKET_LIST, true)) {
            throw new SystemException('Unknown status bucket: '.$sBucket);
        }

        return Db::table(self::STATUSES_TABLE)
            ->whereIn('code', self::STATUS_CODE_MAP[$sBucket])
            ->pluck('id')
            ->map(fn ($iStatusID) => (int) $iStatusID)
            ->all();
    }
}
