<?php namespace Logingrupa\DashboardShopaholic\Classes\DataSource;

use Db;
use Backend;
use BackendAuth;
use SystemException;
use Illuminate\Database\Query\Builder;
use Dashboard\Classes\ReportData;
use Dashboard\Classes\ReportDataOrderRule;
use Dashboard\Classes\ReportDataSourceBase;
use Dashboard\Classes\ReportDimension;
use Dashboard\Classes\ReportFetchData;
use Dashboard\Classes\ReportFetchDataResult;
use Dashboard\Classes\ReportMetric;
use Dashboard\Classes\ReportDataQueryBuilder;
use Logingrupa\DashboardShopaholic\Classes\Helper\ActiveCurrency;
use Logingrupa\DashboardShopaholic\Classes\Helper\StatusBuckets;
use Logingrupa\DashboardShopaholic\Models\Settings;

/**
 * OrdersReportDataSource feeds the October v4 dashboard widgets with
 * Shopaholic order data.
 *
 * Revenue metrics aggregate the plugin's order stat table (exact, promo
 * mechanisms included, written at order time). Profit metrics are estimated
 * live against the CURRENT value of a cost price type - they shift when the
 * import updates prices, and positions without a cost price contribute zero.
 */
class OrdersReportDataSource extends ReportDataSourceBase
{
    const STATS_TABLE = 'logingrupa_dashboardshopaholic_order_stats';
    const ORDERS_TABLE = 'lovata_orders_shopaholic_orders';
    const POSITIONS_TABLE = 'lovata_orders_shopaholic_order_positions';
    const PRICES_TABLE = 'lovata_shopaholic_prices';
    const PRICE_TYPES_TABLE = 'lovata_shopaholic_price_types';
    const STATUSES_TABLE = 'lovata_orders_shopaholic_statuses';
    const PAYMENT_METHODS_TABLE = 'lovata_orders_shopaholic_payment_methods';

    const DIMENSION_STATUS = 'status';
    const DIMENSION_PAYMENT_METHOD = 'payment_method';

    const METRIC_ORDERS_COUNT = 'orders_count';
    const METRIC_TURNOVER = 'turnover';
    const METRIC_PROFIT = 'profit';

    const BUCKET_DIMENSION_MAP = [
        'indicator@orders_unprocessed' => StatusBuckets::UNPROCESSED,
        'indicator@orders_processing' => StatusBuckets::PROCESSING,
        'indicator@orders_shipped' => StatusBuckets::SHIPPED,
        'indicator@orders_canceled' => StatusBuckets::CANCELED,
    ];

    const LANG = 'logingrupa.dashboardshopaholic::lang.';

    const BUCKET_ICON_MAP = [
        StatusBuckets::UNPROCESSED => 'ph ph-tray',
        StatusBuckets::PROCESSING => 'ph ph-clock',
        StatusBuckets::SHIPPED => 'ph ph-truck',
        StatusBuckets::CANCELED => 'ph ph-x-circle',
    ];

    public function __construct()
    {
        $this->registerOrderDimensions();
        $this->registerBucketIndicatorDimensions();
        $this->registerRevenueMetrics();

        if ($this->canViewProfit()) {
            $this->registerProfitMetric();
        }
    }

    protected function fetchData(ReportFetchData $obData): ReportFetchDataResult
    {
        $sDimensionCode = $obData->dimension->getCode();

        if (array_key_exists($sDimensionCode, self::BUCKET_DIMENSION_MAP)) {
            return $this->fetchBucketIndicatorData($obData);
        }

        // ReportDataQueryBuilder, not ReportQueryBuilder: the new builder applies
        // the widget's oc_dimension order rule to the metric-totals query where no
        // dimension column is selected - MySQL rejects it with unknown column
        $obData->orderRule ??= new ReportDataOrderRule(ReportDataOrderRule::ATTR_TYPE_DIMENSION);

        $obBuilder = ReportDataQueryBuilder::fromFetchData(
            $obData,
            self::STATS_TABLE,
            self::STATS_TABLE.'.ordered_at'
        );

        $obBuilder->onConfigureQuery(function (Builder $obQuery, ReportDimension $obDimension, array $arMetricList): void {
            $this->applyDimensionJoins($obQuery, $obDimension);
        });

        return $obBuilder->getFetchDataResult($obData->metricsConfiguration);
    }

    private function registerOrderDimensions(): void
    {
        $this->registerDimension(new ReportDimension(
            ReportDimension::CODE_DATE,
            'ordered_at',
            trans(self::LANG.'dimension.date')
        ));

        $this->registerDimension(new ReportDimension(
            self::DIMENSION_STATUS,
            self::STATUSES_TABLE.'.name',
            trans(self::LANG.'dimension.status')
        ));

        $this->registerDimension(new ReportDimension(
            self::DIMENSION_PAYMENT_METHOD,
            self::PAYMENT_METHODS_TABLE.'.name',
            trans(self::LANG.'dimension.payment_method')
        ));
    }

    private function registerBucketIndicatorDimensions(): void
    {
        foreach (self::BUCKET_DIMENSION_MAP as $sDimensionCode => $sBucket) {
            ReportData::addIndicatorMetrics(
                $this->addCalculatedDimension($sDimensionCode, trans(self::LANG.'bucket_dimension.'.$sBucket))
                    ->setDefaultWidgetConfig([
                        'title' => trans(self::LANG.'bucket.'.$sBucket),
                        'icon' => self::BUCKET_ICON_MAP[$sBucket],
                        'link_text' => trans(self::LANG.'widget.view_orders'),
                    ])
            );
        }
    }

    private function registerRevenueMetrics(): void
    {
        $this->registerMetric(new ReportMetric(
            self::METRIC_ORDERS_COUNT,
            self::STATS_TABLE.'.order_id',
            trans(self::LANG.'metric.orders'),
            ReportMetric::AGGREGATE_COUNT
        ));

        $this->registerMetric(new ReportMetric(
            self::METRIC_TURNOVER,
            self::STATS_TABLE.'.total_price',
            trans(self::LANG.'metric.turnover'),
            ReportMetric::AGGREGATE_SUM,
            $this->currencyFormatOptions()
        ));
    }

    /**
     * "Profit" follows the settings cost price type (always registered so saved
     * widgets never break; unconfigured = constant zero). One extra
     * "Profit vs <price type>" metric per active price type lets any widget
     * pick its cost basis on the fly. VAT handling comes from the price type's
     * own price_includes_vat flag in both cases.
     */
    private function registerProfitMetric(): void
    {
        $arPriceTypeList = Db::table(self::PRICE_TYPES_TABLE)
            ->where('active', 1)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'price_includes_vat'])
            ->keyBy('id');

        $iDefaultCostTypeID = (int) Settings::get('cost_price_type_id');
        $obDefaultCostType = $arPriceTypeList->get($iDefaultCostTypeID);

        $this->registerMetric(new ReportMetric(
            self::METRIC_PROFIT,
            $obDefaultCostType === null
                ? '(0)'
                : $this->makeProfitExpression((int) $obDefaultCostType->id, (bool) $obDefaultCostType->price_includes_vat),
            trans(self::LANG.'metric.profit'),
            ReportMetric::AGGREGATE_SUM,
            $this->currencyFormatOptions()
        ));

        foreach ($arPriceTypeList as $obPriceType) {
            $this->registerMetric(new ReportMetric(
                self::METRIC_PROFIT.'_'.$obPriceType->id,
                $this->makeProfitExpression((int) $obPriceType->id, (bool) $obPriceType->price_includes_vat),
                trans(self::LANG.'metric.profit_vs', ['name' => $obPriceType->name]),
                ReportMetric::AGGREGATE_SUM,
                $this->currencyFormatOptions()
            ));
        }
    }

    /**
     * Correlated subquery: net position revenue minus the current cost price,
     * summed per order. Positions without a cost price row contribute zero so
     * missing import data never inflates profit.
     */
    private function makeProfitExpression(int $iPriceTypeID, bool $bCostWithVat): string
    {
        if ($iPriceTypeID < 1) {
            throw new SystemException('Profit metric requires a positive price type id');
        }

        // 100.0 not 100: SQLite would integer-divide tax_percent / 100 to zero
        $sNetRevenue = 'op.price / (1 + coalesce(op.tax_percent, 0) / 100.0)';
        $sCost = $bCostWithVat
            ? 'pp.price / (1 + coalesce(op.tax_percent, 0) / 100.0)'
            : 'pp.price';

        return sprintf(
            '(select coalesce(sum(case when pp.price is null then 0 else (%s - %s) * op.quantity end), 0)'
            .' from %s op'
            .' left join %s pp on pp.item_id = op.item_id and pp.item_type = op.item_type and pp.price_type_id = %d'
            .' where op.order_id = %s.order_id)',
            $sNetRevenue,
            $sCost,
            self::POSITIONS_TABLE,
            self::PRICES_TABLE,
            $iPriceTypeID,
            self::STATS_TABLE
        );
    }

    /**
     * Bucket counters are absolute (current backlog), intentionally ignoring the
     * dashboard date range - an unprocessed order from last month still needs work.
     */
    private function fetchBucketIndicatorData(ReportFetchData $obData): ReportFetchDataResult
    {
        $sBucket = self::BUCKET_DIMENSION_MAP[$obData->dimension->getCode()];
        $arStatusIDList = StatusBuckets::getStatusIds($sBucket);

        $iCount = empty($arStatusIDList)
            ? 0
            : Db::table(self::ORDERS_TABLE)->whereIn('status_id', $arStatusIDList)->count();

        $bNeedsAttention = $sBucket === StatusBuckets::UNPROCESSED && $iCount > 0;

        $obResult = new ReportFetchDataResult;

        return $obResult->setRows($this->makeResultRow($obData->dimension, [
            ReportData::METRIC_VALUE => $iCount,
            ReportData::METRIC_INDICATOR_ICON_STATUS => $bNeedsAttention
                ? ReportData::INDICATOR_ICON_STATUS_IMPORTANT
                : ReportData::INDICATOR_ICON_STATUS_INFO,
            ReportData::METRIC_LINK_ENABLED => true,
            ReportData::METRIC_LINK_HREF => Backend::url('lovata/ordersshopaholic/orders'),
        ]));
    }

    private function applyDimensionJoins(Builder $obQuery, ReportDimension $obDimension): void
    {
        if ($obDimension->getCode() === self::DIMENSION_STATUS) {
            $obQuery->leftJoin(
                self::STATUSES_TABLE,
                self::STATUSES_TABLE.'.id',
                '=',
                self::STATS_TABLE.'.status_id'
            );
        }

        if ($obDimension->getCode() === self::DIMENSION_PAYMENT_METHOD) {
            $obQuery->leftJoin(
                self::PAYMENT_METHODS_TABLE,
                self::PAYMENT_METHODS_TABLE.'.id',
                '=',
                self::STATS_TABLE.'.payment_method_id'
            );
        }
    }

    private function canViewProfit(): bool
    {
        $obBackendUser = BackendAuth::getUser();

        return $obBackendUser === null
            || $obBackendUser->hasAccess('logingrupa.dashboardshopaholic.view_profit');
    }

    private function currencyFormatOptions(): array
    {
        return [
            'style' => 'currency',
            'currency' => ActiveCurrency::code(),
            'maximumFractionDigits' => 2,
        ];
    }

}
