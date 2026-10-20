<?php namespace Logingrupa\DashboardShopaholic\Classes\Helper;

use InvalidArgumentException;
use October\Rain\Database\Schema\Blueprint;
use Schema;

/**
 * OrderIdIndex adds and drops a first-column order_id index on an upstream
 * order table. Shared by the index migrations; no upstream file is touched.
 */
class OrderIdIndex
{
    /**
     * Add the named order_id index unless the table is missing or an index
     * with order_id as its first column already exists.
     */
    public static function add(string $sTableName, string $sIndexName): void
    {
        self::assertNames($sTableName, $sIndexName);
        if (!Schema::hasTable($sTableName) || self::hasOrderIdIndex($sTableName)) {
            return;
        }

        Schema::table($sTableName, function (Blueprint $obTable) use ($sIndexName): void {
            $obTable->index('order_id', $sIndexName);
        });
    }

    /**
     * Drop the named index; no-op when the table or the index is missing.
     */
    public static function drop(string $sTableName, string $sIndexName): void
    {
        self::assertNames($sTableName, $sIndexName);
        if (!Schema::hasTable($sTableName) || !self::hasIndexNamed($sTableName, $sIndexName)) {
            return;
        }

        Schema::table($sTableName, function (Blueprint $obTable) use ($sIndexName): void {
            $obTable->dropIndex($sIndexName);
        });
    }

    /**
     * True when any index on the table has order_id as its first column.
     */
    private static function hasOrderIdIndex(string $sTableName): bool
    {
        foreach (Schema::getIndexes($sTableName) as $arIndex) {
            $arColumnList = is_array($arIndex) ? ($arIndex['columns'] ?? []) : [];
            if (is_array($arColumnList) && ($arColumnList[0] ?? null) === 'order_id') {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the table has an index with exactly this name.
     */
    private static function hasIndexNamed(string $sTableName, string $sIndexName): bool
    {
        foreach (Schema::getIndexes($sTableName) as $arIndex) {
            if (is_array($arIndex) && ($arIndex['name'] ?? null) === $sIndexName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws InvalidArgumentException when a table or index name is empty
     */
    private static function assertNames(string $sTableName, string $sIndexName): void
    {
        if ($sTableName === '' || $sIndexName === '') {
            throw new InvalidArgumentException('OrderIdIndex requires a table name and an index name');
        }
    }
}
