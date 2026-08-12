<?php namespace Logingrupa\DashboardShopaholic\Classes\DataSource;

use BackendAuth;
use Dashboard\Classes\ReportData;
use Dashboard\Classes\ReportDataOrderRule;
use Dashboard\Classes\ReportDataQueryBuilder;
use Dashboard\Classes\ReportDataSourceBase;
use Dashboard\Classes\ReportDimension;
use Dashboard\Classes\ReportFetchData;
use Dashboard\Classes\ReportFetchDataResult;
use Dashboard\Classes\ReportMetric;
use Db;
use Illuminate\Database\Query\Builder;
use Logingrupa\DashboardShopaholic\Classes\Helper\ActiveCurrency;
use Logingrupa\DashboardShopaholic\Classes\Helper\StatusBuckets;
use Logingrupa\DashboardShopaholic\Models\Settings;
use SystemException;

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
    public const STATS_TABLE = 'logingrupa_dashboardshopaholic_order_stats';
    public const POSITIONS_TABLE = 'lovata_orders_shopaholic_order_positions';
    public const PRICES_TABLE = 'lovata_shopaholic_prices';
    public const PRICE_TYPES_TABLE = 'lovata_shopaholic_price_types';
    public const STATUSES_TABLE = 'lovata_orders_shopaholic_statuses';
    public const PAYMENT_METHODS_TABLE = 'lovata_orders_shopaholic_payment_methods';
    public const SHIPPING_TYPES_TABLE = 'lovata_orders_shopaholic_shipping_types';

    public const DIMENSION_STATUS = 'status';
    public const DIMENSION_PAYMENT_METHOD = 'payment_method';
    public const DIMENSION_SHIPPING_METHOD = 'shipping_method';
    public const DIMENSION_WEEKDAY = 'weekday';
    public const DIMENSION_HOUR = 'hour_of_day';

    public const METRIC_ORDERS_COUNT = 'orders_count';
    public const METRIC_TURNOVER = 'turnover';
    public const METRIC_PROFIT = 'profit';
    public const METRIC_AVG_ORDER_VALUE = 'avg_order_value';
    public const METRIC_CANCEL_RATE = 'cancel_rate';
    public const METRIC_CANCELED_VALUE = 'canceled_value';
    public const METRIC_AVG_ITEMS_PER_ORDER = 'avg_items_per_order';
    public const METRIC_UNITS_SOLD = 'units_sold';
    public const METRIC_RETURNING_CUSTOMER_SHARE = 'returning_customer_share';
    public const METRIC_AVG_HOURS_TO_SHIP = 'avg_hours_to_ship';

    /**
     * ISO weekday order: WEEKDAY() (MySQL) and shifted strftime (SQLite) both
     * yield 0 = Monday.
     */
    public const WEEKDAY_LABEL_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public const DIMENSION_MEDIAN_ORDER_VALUE = 'indicator@median_order_value';

    public const LANG = 'logingrupa.dashboardshopaholic::lang.';

    public function __construct()
    {
        $this->registerOrderDimensions();
        $this->registerMedianIndicatorDimension();
        $this->registerRevenueMetrics();

        if ($this->canViewProfit()) {
            $this->registerProfitMetric();
        }
    }

    protected function fetchData(ReportFetchData $obData): ReportFetchDataResult
    {
        $sDimensionCode = $obData->dimension->getCode();

        if ($sDimensionCode === self::DIMENSION_MEDIAN_ORDER_VALUE) {
            return $this->fetchMedianIndicatorData($obData);
        }

        // ReportDataQueryBuilder, not ReportQueryBuilder: the new builder applies
        // the widget's oc_dimension order rule to the metric-totals query where no
        // dimension column is selected - MySQL rejects it with unknown column
        $obData->orderRule ??= new ReportDataOrderRule(ReportDataOrderRule::ATTR_TYPE_DIMENSION);

        if (in_array($sDimensionCode, [self::DIMENSION_WEEKDAY, self::DIMENSION_HOUR], true)) {
            return $this->fetchExpressionDimensionData($obData);
        }

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

        $this->registerDimension(new ReportDimension(
            self::DIMENSION_SHIPPING_METHOD,
            self::SHIPPING_TYPES_TABLE.'.name',
            trans(self::LANG.'dimension.shipping_method')
        ));

        $this->registerDimension(new ReportDimension(
            self::DIMENSION_WEEKDAY,
            $this->makeWeekdayExpression(),
            trans(self::LANG.'dimension.weekday')
        ));

        $this->registerDimension(new ReportDimension(
            self::DIMENSION_HOUR,
            $this->makeHourExpression(),
            trans(self::LANG.'dimension.hour_of_day')
        ));
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

        $this->registerMetric(new ReportMetric(
            self::METRIC_AVG_ORDER_VALUE,
            self::STATS_TABLE.'.total_price',
            trans(self::LANG.'metric.avg_order_value'),
            ReportMetric::AGGREGATE_AVG,
            $this->currencyFormatOptions()
        ));

        $this->registerMetric(new ReportMetric(
            self::METRIC_CANCEL_RATE,
            $this->makeCancelRateExpression(),
            trans(self::LANG.'metric.cancel_rate'),
            ReportMetric::AGGREGATE_AVG,
            ['style' => 'percent', 'maximumFractionDigits' => 1]
        ));

        $this->registerMetric(new ReportMetric(
            self::METRIC_CANCELED_VALUE,
            $this->makeCanceledValueExpression(),
            trans(self::LANG.'metric.canceled_value'),
            ReportMetric::AGGREGATE_SUM,
            $this->currencyFormatOptions()
        ));

        $this->registerMetric(new ReportMetric(
            self::METRIC_AVG_ITEMS_PER_ORDER,
            self::STATS_TABLE.'.items_quantity',
            trans(self::LANG.'metric.avg_items_per_order'),
            ReportMetric::AGGREGATE_AVG,
            ['maximumFractionDigits' => 1]
        ));

        $this->registerMetric(new ReportMetric(
            self::METRIC_UNITS_SOLD,
            self::STATS_TABLE.'.items_quantity',
            trans(self::LANG.'metric.units_sold'),
            ReportMetric::AGGREGATE_SUM,
            ['maximumFractionDigits' => 0]
        ));

        // AVG over the stored 0/1 flag; Intl percent formatting scales client-side
        $this->registerMetric(new ReportMetric(
            self::METRIC_RETURNING_CUSTOMER_SHARE,
            self::STATS_TABLE.'.is_returning',
            trans(self::LANG.'metric.returning_customer_share'),
            ReportMetric::AGGREGATE_AVG,
            ['style' => 'percent', 'maximumFractionDigits' => 1]
        ));

        // hours_to_ship is precomputed at write time; AVG skips unshipped (null) rows
        $this->registerMetric(new ReportMetric(
            self::METRIC_AVG_HOURS_TO_SHIP,
            self::STATS_TABLE.'.hours_to_ship',
            trans(self::LANG.'metric.avg_hours_to_ship'),
            ReportMetric::AGGREGATE_AVG,
            ['maximumFractionDigits' => 1]
        ));
    }

    /**
     * AVG over a 0/1 flag = cancellation share of orders in the range.
     * Intl percent formatting multiplies by 100 on the client.
     */
    private function makeCancelRateExpression(): string
    {
        $arStatusIDList = StatusBuckets::getStatusIds(StatusBuckets::CANCELED);

        if (empty($arStatusIDList)) {
            return '(0)';
        }

        // 1.0 not 1: SQLite would average integers to zero
        return sprintf(
            '(case when %s.status_id in (%s) then 1.0 else 0 end)',
            self::STATS_TABLE,
            implode(',', $arStatusIDList)
        );
    }

    /**
     * Order value lost to cancellations: total_price of canceled orders, zero
     * for everything else, summed by the aggregate.
     */
    private function makeCanceledValueExpression(): string
    {
        $arStatusIDList = StatusBuckets::getStatusIds(StatusBuckets::CANCELED);

        if (empty($arStatusIDList)) {
            return '(0)';
        }

        return sprintf(
            '(case when %1$s.status_id in (%2$s) then %1$s.total_price else 0 end)',
            self::STATS_TABLE,
            implode(',', $arStatusIDList)
        );
    }

    /**
     * ISO weekday of ordered_at as a sortable translated label ("1 Mon".."7 Sun").
     * MySQL WEEKDAY() is already 0 = Monday; SQLite strftime('%w') starts at
     * Sunday and gets shifted to match.
     */
    private function makeWeekdayExpression(): string
    {
        $sWeekdayNumber = Db::getDriverName() === 'sqlite'
            ? "(cast(strftime('%w', ".self::STATS_TABLE.".ordered_at) as integer) + 6) % 7"
            : 'WEEKDAY('.self::STATS_TABLE.'.ordered_at)';

        $arCaseList = [];
        foreach (self::WEEKDAY_LABEL_KEYS as $iWeekdayIndex => $sLabelKey) {
            $sLabel = ($iWeekdayIndex + 1).' '.trans(self::LANG.'weekday.'.$sLabelKey);
            $arCaseList[] = sprintf(
                "when %d then '%s'",
                $iWeekdayIndex,
                str_replace("'", "''", $sLabel)
            );
        }

        return sprintf('(case %s %s end)', $sWeekdayNumber, implode(' ', $arCaseList));
    }

    /**
     * Hour of ordered_at as a zero-padded "HH:00" label - lexicographic order
     * equals chronological order on both drivers.
     */
    private function makeHourExpression(): string
    {
        return Db::getDriverName() === 'sqlite'
            ? "(strftime('%H', ".self::STATS_TABLE.".ordered_at) || ':00')"
            : "DATE_FORMAT(".self::STATS_TABLE.".ordered_at, '%H:00')";
    }

    /**
     * Weekday/hour dimensions use a raw SQL expression as the dimension column.
     * The stock builders pass that expression through orderBy(), where the SQL
     * grammar identifier-quotes it - MySQL then fails with unknown column
     * (SQLite silently orders by a string constant). Same builder, but any
     * dimension order rule is rebuilt on the oc_dimension select alias.
     */
    private function fetchExpressionDimensionData(ReportFetchData $obData): ReportFetchDataResult
    {
        $obBuilder = ReportDataQueryBuilder::fromFetchData(
            $obData,
            self::STATS_TABLE,
            self::STATS_TABLE.'.ordered_at'
        );

        $obQuery = $obBuilder->initQuery();

        if ($obData->orderRule->getDataAttributeType() === ReportDataOrderRule::ATTR_TYPE_DIMENSION) {
            $obQuery->orders = [];
            $obQuery->orderByRaw('oc_dimension '.($obData->orderRule->isAscending() ? 'asc' : 'desc'));
        }

        $arRecordList = $obData->totalsOnly ? [] : $obQuery->get()->all();

        $obResult = new ReportFetchDataResult($arRecordList);
        $obResult->setMetricTotals($obBuilder->getMetricTotals($obData->metricsConfiguration));

        return $obResult;
    }

    private function registerMedianIndicatorDimension(): void
    {
        ReportData::addIndicatorMetrics(
            $this->addCalculatedDimension(
                self::DIMENSION_MEDIAN_ORDER_VALUE,
                trans(self::LANG.'dimension.median_order_value')
            )->setDefaultWidgetConfig([
                'title' => trans(self::LANG.'widget.median_order_value'),
                'icon' => 'ph ph-chart-bar',
            ])
        );
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
     * Median total_price of orders in the dashboard date range. Same
     * startOfDay/endOfDay window the metric query builder applies, so the card
     * agrees with the charts. No SQL MEDIAN exists in MySQL or SQLite - count
     * plus offset into the sorted set covers both (two middle rows averaged on
     * even counts).
     */
    private function fetchMedianIndicatorData(ReportFetchData $obData): ReportFetchDataResult
    {
        $obQuery = Db::table(self::STATS_TABLE);

        if ($obData->dateStart !== null && $obData->dateEnd !== null) {
            $obQuery->whereBetween('ordered_at', [
                $obData->dateStart->copy()->startOfDay()->toDateTimeString(),
                $obData->dateEnd->copy()->endOfDay()->toDateTimeString(),
            ]);
        }

        $iCount = $obQuery->count();

        $fMedian = 0.0;
        if ($iCount > 0) {
            // total_price is a decimal column - drivers return numeric strings
            $arMiddleList = array_map(
                static fn (mixed $fPrice): float => is_numeric($fPrice) ? (float) $fPrice : 0.0,
                $obQuery
                    ->orderBy('total_price')
                    ->offset(intdiv($iCount - 1, 2))
                    ->limit(2 - ($iCount % 2))
                    ->pluck('total_price')
                    ->all()
            );

            if ($arMiddleList !== []) {
                $fMedian = array_sum($arMiddleList) / count($arMiddleList);
            }
        }

        $obResult = new ReportFetchDataResult();

        return $obResult->setRows($this->makeResultRow($obData->dimension, [
            ReportData::METRIC_VALUE => number_format($fMedian, 2).' '.ActiveCurrency::code(),
            ReportData::METRIC_INDICATOR_ICON_STATUS => ReportData::INDICATOR_ICON_STATUS_INFO,
            ReportData::METRIC_LINK_ENABLED => false,
            ReportData::METRIC_LINK_HREF => '',
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

        if ($obDimension->getCode() === self::DIMENSION_SHIPPING_METHOD) {
            $obQuery->leftJoin(
                self::SHIPPING_TYPES_TABLE,
                self::SHIPPING_TYPES_TABLE.'.id',
                '=',
                self::STATS_TABLE.'.shipping_type_id'
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
