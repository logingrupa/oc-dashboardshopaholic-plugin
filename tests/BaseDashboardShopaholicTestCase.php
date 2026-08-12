<?php

use Carbon\Carbon;
use Dashboard\Classes\ReportFetchData;
use Illuminate\Support\Facades\DB as Db;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use System\Models\SettingModel;

/**
 * Base test case for DashboardShopaholic integration tests.
 *
 * The full Shopaholic migration chain is incompatible with SQLite (see
 * wishlistextender), so only this plugin is migrated and the Lovata tables the
 * data source reads are created as minimal stubs. All queries under test are
 * plain query-builder SQL, so stub tables exercise the real code paths.
 */
abstract class BaseDashboardShopaholicTestCase extends PluginTestCase
{
    public const OFFER_TYPE = 'Lovata\Shopaholic\Models\Offer';

    protected $autoMigrate = false;

    /**
     * Modules must be migrated BEFORE PluginTestCase::setUp() calls
     * Mail::pretend() - resolving the mailer queries system_settings.
     */
    public function createApplication()
    {
        $obApp = parent::createApplication();

        $this->migrateModules();

        return $obApp;
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->createLovataStubTables();
        $this->runPluginMigrations();

        // SettingModel memoizes instances per process; DB rolls back between tests
        SettingModel::clearInternalCache();
    }

    /**
     * Run this plugin's migrations directly. migratePlugin() would cascade into
     * the full Shopaholic dependency chain, which is incompatible with SQLite.
     */
    protected function runPluginMigrations(): void
    {
        require_once __DIR__.'/../updates/create_order_stats_table.php';
        require_once __DIR__.'/../updates/add_order_positions_order_id_index.php';
        require_once __DIR__.'/../updates/add_order_stats_manager_columns.php';

        (new Logingrupa\DashboardShopaholic\Updates\CreateOrderStatsTable())->up();
        (new Logingrupa\DashboardShopaholic\Updates\AddOrderPositionsOrderIdIndex())->up();
        (new Logingrupa\DashboardShopaholic\Updates\AddOrderStatsManagerColumns())->up();
    }

    protected function createLovataStubTables(): void
    {
        $this->createStubTable('lovata_orders_shopaholic_orders', function (Blueprint $obTable) {
            $obTable->increments('id');
            $obTable->integer('user_id')->nullable();
            $obTable->integer('status_id')->nullable();
            $obTable->string('order_number')->nullable();
            $obTable->integer('payment_method_id')->nullable();
            $obTable->integer('shipping_type_id')->nullable();
            $obTable->integer('currency_id')->nullable();
            $obTable->integer('site_id')->nullable();
            $obTable->text('property')->nullable();
            $obTable->timestamps();
        });

        $this->createStubTable('lovata_orders_shopaholic_order_positions', function (Blueprint $obTable) {
            $obTable->increments('id');
            $obTable->integer('order_id');
            $obTable->integer('item_id');
            $obTable->string('item_type');
            $obTable->decimal('price', 15, 2)->nullable();
            $obTable->decimal('old_price', 15, 2)->nullable();
            $obTable->integer('quantity')->nullable();
            $obTable->decimal('tax_percent', 8, 2)->nullable();
            $obTable->timestamps();
        });

        $this->createStubTable('lovata_orders_shopaholic_statuses', function (Blueprint $obTable) {
            $obTable->increments('id');
            $obTable->string('name')->nullable();
            $obTable->string('code')->nullable();
        });

        $this->createStubTable('lovata_orders_shopaholic_payment_methods', function (Blueprint $obTable) {
            $obTable->increments('id');
            $obTable->string('name')->nullable();
            $obTable->string('code')->nullable();
        });

        $this->createStubTable('lovata_orders_shopaholic_shipping_types', function (Blueprint $obTable) {
            $obTable->increments('id');
            $obTable->string('name')->nullable();
            $obTable->string('code')->nullable();
        });

        $this->createStubTable('lovata_shopaholic_price_types', function (Blueprint $obTable) {
            $obTable->increments('id');
            $obTable->boolean('active')->default(true);
            $obTable->string('name')->nullable();
            $obTable->string('code')->nullable();
            $obTable->boolean('price_includes_vat')->default(false);
            $obTable->integer('sort_order')->nullable();
        });

        $this->createStubTable('lovata_shopaholic_prices', function (Blueprint $obTable) {
            $obTable->increments('id');
            $obTable->integer('item_id');
            $obTable->string('item_type');
            $obTable->decimal('price', 15, 2)->nullable();
            $obTable->integer('price_type_id')->nullable();
        });

        $this->createStubTable('lovata_shopaholic_currency', function (Blueprint $obTable) {
            $obTable->increments('id');
            $obTable->string('name')->nullable();
            $obTable->string('code')->nullable();
            $obTable->boolean('is_default')->default(false);
            $obTable->boolean('active')->default(true);
        });
    }

    protected function createStubTable(string $sTableName, callable $fnDefineTable): void
    {
        if (Schema::hasTable($sTableName)) {
            return;
        }

        Schema::create($sTableName, $fnDefineTable);
    }

    /**
     * Seed the NC-like status set and default currency shared by most tests.
     */
    protected function seedBaseData(): void
    {
        Db::table('lovata_orders_shopaholic_statuses')->insert([
            ['id' => 1, 'name' => 'Order received', 'code' => 'new'],
            ['id' => 2, 'name' => 'Waiting for payment', 'code' => 'in_progress'],
            ['id' => 3, 'name' => 'Completed', 'code' => 'complete'],
            ['id' => 4, 'name' => 'Canceled', 'code' => 'canceled'],
            ['id' => 5, 'name' => 'Payment received', 'code' => 'new-payment-received'],
            ['id' => 8, 'name' => 'Sent', 'code' => 'sent'],
        ]);

        Db::table('lovata_shopaholic_currency')->insert([
            ['id' => 1, 'name' => 'EUR', 'code' => 'EUR', 'is_default' => 1, 'active' => 1],
        ]);

        Db::table('lovata_orders_shopaholic_payment_methods')->insert([
            ['id' => 1, 'name' => 'Vipps', 'code' => 'vipps'],
            ['id' => 2, 'name' => 'Invoice', 'code' => 'invoice'],
        ]);
    }

    /**
     * Insert an order row plus its stat row in one call.
     */
    protected function seedOrderWithStat(array $arOrder, float $fTotalPrice): int
    {
        $sCreatedAt = $arOrder['created_at'] ?? Carbon::now()->toDateTimeString();

        $iOrderID = Db::table('lovata_orders_shopaholic_orders')->insertGetId([
            'status_id' => $arOrder['status_id'] ?? 1,
            'order_number' => $arOrder['order_number'] ?? ('T-'.mt_rand(1000, 9999)),
            'payment_method_id' => $arOrder['payment_method_id'] ?? null,
            'shipping_type_id' => $arOrder['shipping_type_id'] ?? null,
            'user_id' => $arOrder['user_id'] ?? null,
            'property' => $arOrder['property'] ?? null,
            'created_at' => $sCreatedAt,
            'updated_at' => $sCreatedAt,
        ]);

        Db::table('logingrupa_dashboardshopaholic_order_stats')->insert([
            'order_id' => $iOrderID,
            'ordered_at' => $sCreatedAt,
            'status_id' => $arOrder['status_id'] ?? 1,
            'payment_method_id' => $arOrder['payment_method_id'] ?? null,
            'shipping_type_id' => $arOrder['shipping_type_id'] ?? null,
            'user_id' => $arOrder['user_id'] ?? null,
            'items_quantity' => $arOrder['items_quantity'] ?? 0,
            'positions_count' => $arOrder['positions_count'] ?? 0,
            'is_returning' => $arOrder['is_returning'] ?? 0,
            'hours_to_ship' => $arOrder['hours_to_ship'] ?? null,
            'total_price' => $fTotalPrice,
        ]);

        return $iOrderID;
    }

    protected function seedPosition(int $iOrderID, int $iItemID, float $fPrice, int $iQuantity, ?float $fTaxPercent): void
    {
        Db::table('lovata_orders_shopaholic_order_positions')->insert([
            'order_id' => $iOrderID,
            'item_id' => $iItemID,
            'item_type' => self::OFFER_TYPE,
            'price' => $fPrice,
            'quantity' => $iQuantity,
            'tax_percent' => $fTaxPercent,
        ]);
    }

    protected function seedCostPrice(int $iItemID, int $iPriceTypeID, float $fPrice): void
    {
        Db::table('lovata_shopaholic_prices')->insert([
            'item_id' => $iItemID,
            'item_type' => self::OFFER_TYPE,
            'price' => $fPrice,
            'price_type_id' => $iPriceTypeID,
        ]);
    }

    /**
     * Build a ReportFetchData the way the dashboard does, minus the HTTP layer.
     */
    protected function makeFetchData(string $sDimensionCode, array $arMetricCodeList, Carbon $obDateStart, Carbon $obDateEnd): ReportFetchData
    {
        $obData = new ReportFetchData();
        $obData->dimensionCode = $sDimensionCode;
        $obData->metricCodes = $arMetricCodeList;
        $obData->metricsConfiguration = [];
        $obData->dimensionFilters = [];
        $obData->hideEmptyDimensionValues = false;
        $obData->dateStart = $obDateStart;
        $obData->dateEnd = $obDateEnd;

        return $obData;
    }
}
