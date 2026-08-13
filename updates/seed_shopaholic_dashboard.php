<?php namespace Logingrupa\DashboardShopaholic\Updates;

use Dashboard\Models\Dashboard;
use Logingrupa\DashboardShopaholic\Classes\Helper\DashboardOwner;
use Logingrupa\DashboardShopaholic\Classes\Helper\DefaultDashboardLayout;
use October\Rain\Database\Updates\Migration;

/**
 * Seed the "Shopaholic Dashboard" on the MAIN backend dashboard with the stock
 * layout. Creates the dashboard row if missing, fills the layout only when the
 * definition is empty - a user-customized layout is never overwritten.
 */
class SeedShopaholicDashboard extends Migration
{
    public const DASHBOARD_CODE = 'shopaholic-dash';
    public const OWNER_TYPE = \Dashboard\Controllers\Index::class;

    public function up(): void
    {
        $obDashboard = Dashboard::applyOwner(self::OWNER_TYPE)
            ->where('code', self::DASHBOARD_CODE)
            ->first();

        if ($obDashboard === null) {
            $obDashboard = new Dashboard();
            $obDashboard->owner_type = self::OWNER_TYPE;
            $obDashboard->code = self::DASHBOARD_CODE;
            $obDashboard->name = 'Shopaholic Dashboard';
            $obDashboard->icon = 'ph ph-shopping-cart';
            $obDashboard->is_global = true;
            $obDashboard->is_custom = true;
            $obDashboard->default_start = 'month';
            $obDashboard->default_end = 'today';
            $obDashboard->default_interval = 'day';
            $obDashboard->default_compare = 'prev-period';
            // CLI seeding has no signed-in user; without an owner the row is
            // invisible in the backend Dashboards manage list
            $obDashboard->created_user_id = DashboardOwner::resolveUserId();
            $obDashboard->updated_user_id = $obDashboard->created_user_id;
        }

        // Never clobber a layout the user already saved
        if (empty($obDashboard->definition)) {
            $obDashboard->definition = DefaultDashboardLayout::makeDefinition();
        }

        $obDashboard->forceSave();
    }

    public function down(): void
    {
        // Leave the dashboard in place - it may hold user customizations
    }
}
