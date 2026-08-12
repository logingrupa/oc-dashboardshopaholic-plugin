# Logingrupa.DashboardShopaholic

Backend shop dashboard: orders report data source (period metrics: orders, turnover,
average and median order value, cancel rate, price-type profit), "New Orders" Vue report
widget, order_stats fact table kept in sync by events.
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
                       mapping), NewOrdersDataBuilder (widget payload), ActiveCurrency,
                       DefaultDashboardLayout (stock + legacy dashboard definitions,
                       shared by seed and period-cards migrations)
- console/             BackfillOrderStats (`php artisan dashboardshopaholic.backfill`)
- models/              OrderStat, Settings (cost price type setting, registerSettings())
- vuecomponents/       NewOrdersList report widget (+ neworderslist partials/assets)
- updates/             create_order_stats_table, order_positions order_id index,
                       seed_shopaholic_dashboard (seeds default dashboard layout),
                       add_price_types_vat_flag, update_dashboard_period_cards
                       (swaps stock layout to period cards, strips dead bucket widgets
                       from customized layouts)

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
- Backfill on prod-size stores (30k+ orders) exhausts the default 512M CLI memory
  limit around 31k orders despite per-chunk processor resets - run it as
  `php8.4 -d memory_limit=2G artisan dashboardshopaholic:backfill --chunk=500`.
  The writer is updateOrCreate-idempotent, so a died run is safely re-run in full.
- On nailscosmetics.lv2 the default `php` CLI is 8.3 and the app requires 8.4
  (symfony 8 + customxmlimportpricing pin) - always `php8.4` there.
