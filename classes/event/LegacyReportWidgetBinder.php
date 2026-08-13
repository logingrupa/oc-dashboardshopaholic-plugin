<?php namespace Logingrupa\DashboardShopaholic\Classes\Event;

use Dashboard\Behaviors\DashController;
use October\Rain\Html\Helper as HtmlHelper;

/**
 * LegacyReportWidgetBinder restores AJAX for legacy report widgets hosted on the
 * October v4 Vue dashboard.
 *
 * The Vue dashboard posts _dash_definition with every data request, so the
 * DashController behavior knows which Dash widget to build and bind. Buttons
 * INSIDE a legacy (static) report widget bypass the Vue data layer: the AJAX
 * framework serializes only the widget's own form plus the ancestor
 * data-request-data (widget_config with reportName and widgetClass), while the
 * _dash_definition hidden input lives outside that form and never reaches the
 * server. Without it Dashboard\Behaviors\DashController::initDash() builds no
 * Dash widget and October fails with "A widget with class name '...' has not
 * been bound to the controller".
 *
 * This listener recovers the dashboard code from the widget alias prefix
 * (report widget aliases are camel_case('dash '.nameToId(code)) followed by
 * studly(reportName)) and calls initDash() before the handler runs.
 * Dash::bindToController() then binds persisted report widgets from the saved
 * definition and unsaved ones via bindPendingReportWidget() from the posted
 * widget_config. Once October ships _dash_definition inside the static widget
 * request data, the post('_dash_definition') guard turns this into a no-op.
 */
class LegacyReportWidgetBinder
{
    /**
     * bindDashboardForHandler binds the owning Dash widget for a legacy report
     * widget AJAX handler when the request carries no _dash_definition.
     * Listener for backend.ajax.beforeRunHandler; must stay void so the event
     * never halts and October continues to the real handler.
     */
    public function bindDashboardForHandler($obController, string $sHandler): void
    {
        if (strpos($sHandler, '::') === false) {
            return;
        }

        // Vue data requests already carry the definition, nothing to recover
        if (post('_dash_definition')) {
            return;
        }

        $arWidgetConfig = (array) post('widget_config');
        if (empty($arWidgetConfig['reportName']) || empty($arWidgetConfig['widgetClass'])) {
            return;
        }

        if (!$obController->isClassExtendedWith(DashController::class)) {
            return;
        }

        [$sWidgetAlias] = explode('::', $sHandler);

        // Longest matching alias prefix wins so a dashboard code that prefixes
        // another (dashShop vs dashShopExtra) resolves to the exact dashboard
        $sMatchedCode = null;
        $iMatchedLength = 0;
        foreach (array_keys((array) $obController->dashGetConfig()) as $sDashboardCode) {
            $sDashboardAlias = camel_case('dash '.HtmlHelper::nameToId($sDashboardCode));
            $iAliasLength = strlen($sDashboardAlias);
            if ($iAliasLength > $iMatchedLength && str_starts_with($sWidgetAlias, $sDashboardAlias)) {
                $sMatchedCode = $sDashboardCode;
                $iMatchedLength = $iAliasLength;
            }
        }

        if ($sMatchedCode === null) {
            return;
        }

        $obController->initDash($sMatchedCode);
    }
}
