<?php

use Logingrupa\DashboardShopaholic\Classes\Helper\DefaultDashboardLayout;
use Logingrupa\DashboardShopaholic\Updates\UpdateDashboardManagerCards;

require_once __DIR__.'/../../updates/update_dashboard_manager_cards.php';

/**
 * Pure-array tests for the manager-cards layout migration transform.
 */
class UpdateDashboardManagerCardsTest extends BaseDashboardShopaholicTestCase
{
    public function testStockPeriodCardsLayoutSwappedWholesale(): void
    {
        $obMigration = new UpdateDashboardManagerCards();

        $arResult = $obMigration->transformDefinition(DefaultDashboardLayout::makePeriodCardsDefinition());

        $this->assertSame(DefaultDashboardLayout::makeDefinition(), $arResult);
    }

    public function testNewStockLayoutHasManagerCardRow(): void
    {
        $arDefinition = DefaultDashboardLayout::makeDefinition();

        $arRowReportNames = array_map(
            static fn (array $arWidget): string => $arWidget['reportName'],
            $arDefinition[1]['widgets']
        );

        $this->assertSame(
            ['units_sold', 'avg_items_per_order', 'returning_customer_share', 'avg_hours_to_ship', 'canceled_value'],
            $arRowReportNames
        );
    }

    public function testCustomizedLayoutUntouched(): void
    {
        $arCustom = [
            ['widgets' => [
                ['reportName' => 'my_chart', 'dimension' => 'date', 'configuration' => ['dimension' => 'date']],
            ]],
        ];

        $obMigration = new UpdateDashboardManagerCards();

        $this->assertSame($arCustom, $obMigration->transformDefinition($arCustom));
    }
}
