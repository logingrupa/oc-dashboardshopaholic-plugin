# Dashboard for Shopaholic

October CMS v4 dashboard data source and widget for the Lovata Shopaholic ecosystem. No pages, no menus, no settings - it plugs into the built-in dashboard system.

## What you get

- **Shopaholic Orders data source** for the built-in Indicator / Chart / Table widgets. Dimensions: date, order status, payment method, plus four `indicator@orders_*` bucket counters (Unprocessed / Processing / Shipped / Canceled backlog, date-range independent). Metrics: Orders, Turnover (incl VAT), Profit (approx, ex VAT).
- **New Orders vue widget** ("Shopaholic: New Orders") - unprocessed orders inside the dashboard interval selection, with customer, total, payment method, linking to the backend order page.
- **Order stat fact table** (`logingrupa_dashboardshopaholic_order_stats`) - one row per order (order_id, ordered_at, status_id, payment_method_id, total_price), kept in sync by events.

## Accuracy model - read this

- **Turnover is exact.** Order totals are computed by the Lovata promo mechanism processor (position discounts, free shipping, campaign prices) once at order time and persisted to the fact table. Raw SQL over order positions would be wrong - discounts live in mechanism rows, not position prices.
- **Profit is an estimate.** Profit = net position revenue minus the CURRENT price of the configured cost price type. Revenue VAT is stripped per position via `tax_percent` - the per-product VAT rate Shopaholic snapshots from the taxes setup at order time. If the cost price type is flagged "prices include VAT", its VAT is stripped the same way. Profit shifts whenever the price import updates cost prices, and positions without a cost price row contribute zero (missing data never inflates profit).

## Install

```bash
php artisan october:migrate
php artisan dashboardshopaholic:backfill   # build stat rows for existing orders
```

New and edited orders keep the fact table in sync automatically. Re-run the backfill after any manual order/mechanism surgery in the DB.

## Use

On the main backend dashboard press Edit, add an Indicator / Chart / Table bound to the "Shopaholic Orders" data source, or add the "Shopaholic: New Orders" widget. To keep a dedicated shop dashboard, create one under Dashboard > Manage dashboards and compose it from the same widgets.

## Configure

- **Profit cost basis**: Settings > Shopaholic > Dashboard for Shopaholic - pick the cost price type (e.g. Izplatītāju cena). Empty = no Profit metric in the dropdown.
- **Cost VAT flag**: Shopaholic > Price Types - each price type has a "Prices include VAT (gross)" switch (also shown as a list column). Set it on the cost price type so profit strips VAT from cost when needed.
- **Status buckets** map by status CODE: `new*` = Unprocessed, `in_progress` = Processing, `sent`/`complete` = Shipped, `canceled` = Canceled (`Classes\Helper\StatusBuckets`).

## Permissions

- `logingrupa.dashboardshopaholic.access_dashboard` - see the New Orders widget
- `logingrupa.dashboardshopaholic.view_profit` - profit metrics are not even registered without it

## Tests

```bash
cd plugins/logingrupa/dashboardshopaholic
../../../vendor/bin/phpunit
```

Integration tests run on SQLite in-memory with stub Shopaholic tables and assert exact metric math (turnover, profit, date ranges, chart totals). The promo-processor write path is covered by a real-data smoke check instead of mocks.
