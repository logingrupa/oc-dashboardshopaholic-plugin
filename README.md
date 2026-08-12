# Dashboard for Shopaholic

October CMS v4 dashboard data source and widget for the Lovata Shopaholic ecosystem. No pages, no menus - it plugs into the built-in dashboard system. Backend in English, Latvian, Russian and Norwegian.

## Install

Latest tagged release compatible with your October CMS version:

```bash
php artisan plugin:install Logingrupa.DashboardShopaholic --from=git@github.com:logingrupa/oc-dashboardshopaholic-plugin.git --oc
```

Development version (master branch):

```bash
php artisan plugin:install Logingrupa.DashboardShopaholic --from=git@github.com:logingrupa/oc-dashboardshopaholic-plugin.git --want=dev-master --oc
```

The `--oc` flag is required because the composer package name carries the `oc-*-plugin` prefix (`logingrupa/oc-dashboardshopaholic-plugin`).

Composer alternative - add to your project `composer.json` and update:

```json
"repositories": [
    {
        "name": "logingrupa/oc-dashboardshopaholic-plugin",
        "type": "git",
        "url": "git@github.com:logingrupa/oc-dashboardshopaholic-plugin.git"
    }
]
```

```bash
composer require logingrupa/oc-dashboardshopaholic-plugin:^1.3
```

Then on every server:

```bash
php artisan october:migrate
php artisan dashboardshopaholic:backfill   # one-time: build stat rows for existing orders
```

After install: pick the cost price type in Settings > Catalog configuration > Dashboard, and flip the "Prices include VAT" switch on your cost price type under Shopaholic > Price Types.

## What you get

- **Shopaholic Orders data source** for the built-in Indicator / Chart / Table widgets. Dimensions: date, order status, payment method, plus an `indicator@median_order_value` card (median order total for the selected date range). Metrics: Orders, Turnover (incl VAT), Average order value, Cancellation rate, Profit (approx, ex VAT) following the settings cost price type, and a "Profit vs <price type>" metric per active price type so any widget can pick its cost basis on the fly. Metric-based indicator cards respect the dashboard date range and show the previous-period arrow when Compare Totals is on.
- **New Orders widget** - unprocessed orders inside the dashboard interval selection, with customer, total, payment method, linking to the backend order page.
- **Seeded dashboard** - a migration seeds a global "Shopaholic Dashboard" (period cards: orders, average and median order value, cancel rate; New Orders queue, Turnover & Profit chart, by-status and by-payment tables). A customized layout is never overwritten.
- **Order stat fact table** (`logingrupa_dashboardshopaholic_order_stats`) - one row per order (order_id, ordered_at, status_id, payment_method_id, total_price), kept in sync by order events.

## Accuracy model - read this

- **Turnover is exact.** Order totals are computed by the Lovata promo mechanism processor (position discounts, free shipping, campaign prices) once at order time and persisted to the fact table. Raw SQL over order positions would be wrong - discounts live in mechanism rows, not position prices.
- **Profit is an estimate.** Profit = net position revenue minus the CURRENT price of the chosen cost price type. Revenue VAT is stripped per position via `tax_percent` - the per-product VAT rate Shopaholic snapshots from the taxes setup at order time. If the cost price type is flagged "prices include VAT", its VAT is stripped the same way. Profit shifts whenever the price import updates cost prices, and positions without a cost price row contribute zero (missing data never inflates profit).

## Configure

- **Profit cost basis**: Settings > Catalog configuration > Dashboard - pick the cost price type (e.g. a distributor price type). The plain Profit metric follows it; unconfigured it reads zero.
- **Cost VAT flag**: Shopaholic > Price Types - each price type has a "Prices include VAT (gross)" switch (also a list column). Wrong flag = profit off by exactly the VAT amount.
- **Status buckets** map by status CODE: `new*` = Unprocessed (feeds the New Orders widget), `canceled` = Canceled (feeds the Cancellation rate metric) (`Classes\Helper\StatusBuckets`).

## Permissions

- `logingrupa.dashboardshopaholic.access_dashboard` - see the New Orders widget
- `logingrupa.dashboardshopaholic.view_profit` - profit metrics and the settings page

## Tests

```bash
cd plugins/logingrupa/dashboardshopaholic
../../../vendor/bin/phpunit
```

Integration tests run on SQLite in-memory with stub Shopaholic tables and assert exact metric math (turnover, profit net/gross, per-type VAT flags, date ranges, chart totals). The promo-processor write path is covered by a real-data smoke check instead of mocks.

## Releases

Git tags (`v1.3.0`, ...) are the composer versions. `plugin:install` without `--want` resolves the highest tag whose constraints match your October installation (`october/system ^4.0` for the 1.x line); `--want=dev-master` tracks master.
