<?php namespace Logingrupa\DashboardShopaholic\Classes\Helper;

use Backend\Models\User as BackendUser;

/**
 * Resolves the backend user that should own the seeded dashboard row.
 *
 * CLI migrations run with no signed-in user, but the backend Dashboards
 * manage list (Dashboard\Controllers\Dashboards::listExtendQuery) only shows
 * rows where created_user_id matches the signed-in user or is_system is set.
 * An ownerless seeded row therefore renders on the dashboard itself yet can
 * never be reordered or edited from the manage list. Shared by the seed
 * migration (fresh installs) and the owner-fix migration (existing installs).
 */
class DashboardOwner
{
    public static function resolveUserId(): ?int
    {
        // v1-imported stores hold 0/1/2 in is_superuser - a boolean bind (= 1)
        // would skip the 2s. Prefer activated superusers, lowest id wins.
        $mUserId = BackendUser::where('is_superuser', '!=', 0)
            ->orderByDesc('is_activated')
            ->orderBy('id')
            ->value('id')
            ?? BackendUser::orderBy('id')->value('id');

        return is_numeric($mUserId) ? (int) $mUserId : null;
    }
}
