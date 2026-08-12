<?php

use Logingrupa\DashboardShopaholic\Classes\Helper\DefaultDashboardLayout;
use Logingrupa\DashboardShopaholic\Updates\UpdateDashboardPeriodCards;

require_once __DIR__.'/../../updates/update_dashboard_period_cards.php';

/**
 * Pure-array tests for the period-cards layout migration transform.
 */
class UpdateDashboardPeriodCardsTest extends BaseDashboardShopaholicTestCase
{
    public function testStockLegacyLayoutSwappedWholesale(): void
    {
        $obMigration = new UpdateDashboardPeriodCards();

        $arResult = $obMigration->transformDefinition(DefaultDashboardLayout::makeLegacyDefinition());

        $this->assertSame(DefaultDashboardLayout::makeDefinition(), $arResult);
    }

    public function testCustomizedLayoutOnlyLosesDeadWidgets(): void
    {
        $arCustom = [
            ['widgets' => [
                ['reportName' => 'my_chart', 'dimension' => 'date', 'configuration' => ['dimension' => 'date']],
                ['reportName' => 'shipped', 'dimension' => 'indicator@orders_shipped', 'configuration' => ['dimension' => 'indicator@orders_shipped']],
            ]],
            ['widgets' => [
                ['reportName' => 'canceled', 'configuration' => ['dimension' => 'indicator@orders_canceled']],
            ]],
        ];

        $obMigration = new UpdateDashboardPeriodCards();

        $arResult = $obMigration->transformDefinition($arCustom);

        // Dead cards gone, the emptied row gone with them, custom widget kept
        $this->assertCount(1, $arResult);
        $this->assertCount(1, $arResult[0]['widgets']);
        $this->assertSame('my_chart', $arResult[0]['widgets'][0]['reportName']);
    }

    public function testCustomizedLayoutWithoutDeadWidgetsUntouched(): void
    {
        $arCustom = [
            ['widgets' => [
                ['reportName' => 'my_chart', 'dimension' => 'date', 'configuration' => ['dimension' => 'date']],
            ]],
        ];

        $obMigration = new UpdateDashboardPeriodCards();

        $this->assertSame($arCustom, $obMigration->transformDefinition($arCustom));
    }
}
