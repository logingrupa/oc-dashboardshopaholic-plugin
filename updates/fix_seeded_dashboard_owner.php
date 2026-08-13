<?php namespace Logingrupa\DashboardShopaholic\Updates;

use Illuminate\Support\Facades\Db;
use Logingrupa\DashboardShopaholic\Classes\Helper\DashboardOwner;
use October\Rain\Database\Updates\Migration;

/**
 * The 1.1.0 seed migration ran from the deploy CLI, so on production the
 * seeded "Shopaholic Dashboard" row was created with created_user_id NULL.
 * The backend Dashboards manage list only shows rows owned by the signed-in
 * user (or is_system rows), so the ownerless row rendered as a dashboard but
 * was invisible in the manage list - impossible to reorder or edit. Assign
 * the first superuser as owner wherever the seed left NULL.
 */
class FixSeededDashboardOwner extends Migration
{
    public function up(): void
    {
        $iUserId = DashboardOwner::resolveUserId();
        if ($iUserId === null) {
            return;
        }

        Db::table('dashboard_dashboards')
            ->where('code', 'shopaholic-dash')
            ->where('owner_type', \Dashboard\Controllers\Index::class)
            ->whereNull('created_user_id')
            ->update([
                'created_user_id' => $iUserId,
                'updated_user_id' => $iUserId,
            ]);
    }

    public function down(): void
    {
        // Ownership is a repair, not a schema change - nothing to revert
    }
}
