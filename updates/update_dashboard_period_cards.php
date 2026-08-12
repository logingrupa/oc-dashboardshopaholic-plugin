<?php namespace Logingrupa\DashboardShopaholic\Updates;

use Dashboard\Models\Dashboard;
use Logingrupa\DashboardShopaholic\Classes\Helper\DefaultDashboardLayout;
use October\Rain\Database\Updates\Migration;

/**
 * Replace the all-time status backlog cards with period-scoped cards:
 * orders in period, average and median order value, cancel rate.
 *
 * An untouched stock layout is swapped wholesale for the new stock layout.
 * A customized layout only loses the widgets whose indicator dimensions
 * no longer exist (they would error) - the new cards are available in the
 * dashboard editor for the user to place.
 */
class UpdateDashboardPeriodCards extends Migration
{
    public const REMOVED_DIMENSION_LIST = [
        'indicator@orders_unprocessed',
        'indicator@orders_processing',
        'indicator@orders_shipped',
        'indicator@orders_canceled',
    ];

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
        $sLegacyJson = json_encode(DefaultDashboardLayout::makeLegacyDefinition());

        if (json_encode($arDefinition) === $sLegacyJson) {
            return DefaultDashboardLayout::makeDefinition();
        }

        return $this->removeDeadWidgets($arDefinition);
    }

    private function removeDeadWidgets(array $arDefinition): array
    {
        $arResult = [];

        foreach ($arDefinition as $arRow) {
            if (!is_array($arRow) || !isset($arRow['widgets']) || !is_array($arRow['widgets'])) {
                $arResult[] = $arRow;
                continue;
            }

            $arRow['widgets'] = array_values(array_filter(
                $arRow['widgets'],
                fn ($arWidget) => !$this->isDeadWidget($arWidget)
            ));

            if (empty($arRow['widgets'])) {
                continue;
            }

            $arResult[] = $arRow;
        }

        return $arResult;
    }

    private function isDeadWidget(mixed $arWidget): bool
    {
        if (!is_array($arWidget)) {
            return false;
        }

        $sDimension = $arWidget['dimension']
            ?? $arWidget['configuration']['dimension']
            ?? null;

        return in_array($sDimension, self::REMOVED_DIMENSION_LIST, true);
    }
}
