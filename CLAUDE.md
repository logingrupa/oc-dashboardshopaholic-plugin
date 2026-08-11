# Logingrupa.DashboardShopaholic

Backend shop dashboard: orders report data source (status buckets, turnover, price-type
profit), "New Orders" Vue report widget, order_stats fact table kept in sync by events.
Namespace Logingrupa\DashboardShopaholic, composer package
logingrupa/oc-dashboardshopaholic-plugin. Requires Lovata.Toolbox, Lovata.Shopaholic,
Lovata.OrdersShopaholic. README.md documents the metrics.

## Environment

- Parent app: C:\laragon\www\nc.
- This plugin dir is its OWN git repo - commit here, not in the root repo.

## Architecture map

- classes/datasource/  OrdersReportDataSource (registered with DashManager, backend only)
- classes/event/       OrderStatHandler (order events -> order_stats fact rows)
- classes/helper/      OrderStatWriter (writes fact rows), StatusBuckets (status -> bucket
                       mapping), NewOrdersDataBuilder (widget payload), ActiveCurrency
- console/             BackfillOrderStats (`php artisan dashboardshopaholic.backfill`)
- models/              OrderStat, Settings (cost price type setting, registerSettings())
- vuecomponents/       NewOrdersList report widget (+ neworderslist partials/assets)
- updates/             create_order_stats_table, order_positions order_id index,
                       seed_shopaholic_dashboard (seeds default dashboard layout),
                       add_price_types_vat_flag

Plugin boot() extends Lovata\Shopaholic\Models\PriceType with price_includes_vat + form injection.

Permissions: logingrupa.dashboardshopaholic.access_dashboard, .view_profit.

## Quality gates

Own phpunit.xml with own tests/bootstrap.php; integration tests in tests/integration.
From plugin dir:

```bash
php ../../../vendor/bin/phpunit
```

composer lint does NOT cover this plugin (phpcs.xml scope excludes plugins/logingrupa) - fix
phpcs.xml scope or lint manually; `vendor/bin/phpcs --standard=phpcs.xml <plugin path>` won't
work either since the ruleset pins files; note as known gap.

## Ship

Ship via /nc-ship (root CLAUDE.md release flow); package logingrupa/oc-dashboardshopaholic-plugin.

## Conventions

Root CLAUDE.md governs: Hungarian notation, Store -> Collection -> Item read path, Tiger-Style.

## Gotchas

- OrdersReportDataSource registers only when `$this->app->runningInBackend()` - it does
  not exist on frontend requests or in plain console context.
- After changing bucket/metric logic, re-run the backfill command so historical
  order_stats rows match the new definition.
