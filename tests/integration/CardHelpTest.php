<?php

use Logingrupa\DashboardShopaholic\Classes\Helper\CardHelp;

/**
 * The card help payload must cover every seeded indicator card and resolve
 * to real translated text, or the client script silently decorates nothing.
 */
class CardHelpTest extends BaseDashboardShopaholicTestCase
{
    public function testPayloadCoversAllSeededCards(): void
    {
        $arPayload = CardHelp::makePayload();

        $this->assertNotSame('', $arPayload['now']);
        $this->assertNotSame('', $arPayload['before']);
        $this->assertCount(9, $arPayload['cards']);

        foreach ($arPayload['cards'] as $sTitle => $sHelp) {
            // an unresolved key still contains the lang namespace
            $this->assertStringNotContainsString('::lang.', $sTitle);
            $this->assertStringNotContainsString('::lang.', $sHelp);
            $this->assertNotSame('', $sHelp);
        }
    }
}
