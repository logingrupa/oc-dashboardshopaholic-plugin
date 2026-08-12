<?php namespace Logingrupa\DashboardShopaholic\Classes\Helper;

/**
 * CardHelp builds the payload for the dashboard card help tooltips.
 *
 * The dashboard Vue widgets have no help/tooltip extension point, so the
 * plugin decorates the rendered cards with a small script. Cards are matched
 * by their TRANSLATED title (the definition stores lang keys, the client
 * renders the resolved text), so the payload maps translated title to
 * translated explanation for the current backend locale.
 */
class CardHelp
{
    public const LANG = 'logingrupa.dashboardshopaholic::lang.';

    /**
     * Widget title key => help text key, both under the plugin lang namespace.
     */
    public const CARD_HELP_MAP = [
        'widget.orders_period' => 'help.orders_period',
        'widget.avg_order_value' => 'help.avg_order_value',
        'widget.median_order_value' => 'help.median_order_value',
        'widget.cancel_rate' => 'help.cancel_rate',
        'widget.units_sold' => 'help.units_sold',
        'widget.avg_items' => 'help.avg_items',
        'widget.returning_share' => 'help.returning_share',
        'widget.hours_to_ship' => 'help.hours_to_ship',
        'widget.canceled_value' => 'help.canceled_value',
    ];

    /**
     * @return array{now: string, before: string, cards: array<string, string>}
     */
    public static function makePayload(): array
    {
        $arCardMap = [];
        foreach (self::CARD_HELP_MAP as $sTitleKey => $sHelpKey) {
            $arCardMap[trans(self::LANG.$sTitleKey)] = trans(self::LANG.$sHelpKey);
        }

        return [
            'now' => trans(self::LANG.'help.now'),
            'before' => trans(self::LANG.'help.before'),
            'cards' => $arCardMap,
        ];
    }
}
