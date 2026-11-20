<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0360 D-8: `quotes.updateLockedLines` lets its holder change an offer
 * row assigned to a work order. Created here so the super-admin sees it right
 * after the deploy, without waiting for `permissions:sync` (QuotePolicy
 * declares it too). Granted to no role: the super-admin passes Gate::before.
 * A fresh installation (no quote permission yet) is left to permissions:sync.
 */
return new class extends Migration
{
    private const string PERMISSION = 'quotes.updateLockedLines';

    private const string INSTALLED_MARKER = 'quotes.update';

    private const string GUARD = 'web';

    public function up(): void
    {
        if (! DB::table('permissions')->where('name', self::INSTALLED_MARKER)->where('guard_name', self::GUARD)->exists()) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'name' => self::PERMISSION,
            'guard_name' => self::GUARD,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', self::GUARD)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
