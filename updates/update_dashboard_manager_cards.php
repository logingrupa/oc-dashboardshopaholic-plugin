<?php namespace Logingrupa\DashboardShopaholic\Updates;

use Dashboard\Models\Dashboard;
use Logingrupa\DashboardShopaholic\Classes\Helper\DefaultDashboardLayout;
use October\Rain\Database\Updates\Migration;

/**
 * Add the manager card row (units sold, items per order, returning share,
 * hours to ship, canceled value) to the stock dashboard.
 *
 * An untouched 1.4.0 period-cards layout is swapped wholesale for the new
 * stock layout. A customized layout stays untouched - every widget in it is
 * still valid, and the new cards are available in the dashboard editor.
 */
class UpdateDashboardManagerCards extends Migration
{
    public function up(): void
    {
        foreach (Dashboard::all() as $obDashboard) {
            $arDefinition = $obDashboard->definition;

            if (empty($arDefinition) || !is_array($arDefinition)) {
                continue;
            }

            $arUpdated = $this->transformDefinition($arDefinition);

            if ($arUpdated === $arDefinition) {
                continue;
            }

            $obDashboard->definition = $arUpdated;
            $obDashboard->forceSave();
        }
    }

    public function down(): void
    {
        // Layout-only change, nothing to restore
    }

    /**
     * Public so the transform is testable without a dashboard row.
     */
    public function transformDefinition(array $arDefinition): array
    {
        $sPeriodCardsJson = json_encode(DefaultDashboardLayout::makePeriodCardsDefinition());

        if (json_encode($arDefinition) === $sPeriodCardsJson) {
            return DefaultDashboardLayout::makeDefinition();
        }

        return $arDefinition;
    }
}
