<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('chartUserWith')) {
    /**
     * A user holding exactly the given chart-of-accounts abilities, as full permission
     * names (e.g. `chart-accounts.view`, `revenue-types.create`, `cost-types.update`).
     *
     * @param  array<int, string>  $permissions
     */
    function chartUserWith(array $permissions): User
    {
        foreach (['chart-accounts', 'revenue-types', 'cost-types'] as $resource) {
            foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
                Permission::findOrCreate("{$resource}.{$ability}", 'web');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}

if (! function_exists('resetChartOfAccounts')) {
    /**
     * Empties the chart the migration seeds (cost types are kept, unplaced), so a
     * test can assert on the exact content it builds.
     */
    function resetChartOfAccounts(): void
    {
        DB::table('treasury_types')->delete();
        DB::table('revenue_types')->delete();
        DB::table('cost_types')->update(['chart_account_id' => null, 'code' => null]);
        DB::table('chart_accounts')->where('level', 'account')->delete();
        DB::table('chart_accounts')->delete();
    }
}
