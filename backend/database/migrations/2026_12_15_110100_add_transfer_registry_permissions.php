<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0404 B-D1: `transferRegistry` moves an offer to another anagrafica, one
 * permission per module that can do it. Created here so the super-admin sees
 * them right after the deploy, without waiting for `permissions:sync`
 * (QuotePolicy and RequestManagementPolicy declare them too). Granted to no
 * role: the super-admin passes Gate::before. A fresh installation (module
 * permissions not created yet) is left to permissions:sync.
 */
return new class extends Migration
{
    private const array RESOURCES = ['quotes', 'request-management', 'enrollee-management'];

    private const string ABILITY = 'transferRegistry';

    private const string GUARD = 'web';

    public function up(): void
    {
        foreach (self::RESOURCES as $resource) {
            if (! DB::table('permissions')->where('name', "{$resource}.update")->where('guard_name', self::GUARD)->exists()) {
                continue;
            }

            DB::table('permissions')->insertOrIgnore([
                'name' => $resource.'.'.self::ABILITY,
                'guard_name' => self::GUARD,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', array_map(static fn (string $resource): string => $resource.'.'.self::ABILITY, self::RESOURCES))
            ->where('guard_name', self::GUARD)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
