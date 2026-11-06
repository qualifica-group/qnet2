<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0365 D-3: `agent-sales-report.view` opens the "Report Vendite Agenti". Creates
 * the permission on installations that have not run `permissions:sync` yet, so
 * a role can be granted it right away. A fresh database has no permission yet
 * (`permissions:sync` creates the whole catalogue): nothing to do there. Nobody
 * receives it automatically: it is a new page and the grant is an administrative choice.
 */
return new class extends Migration
{
    private const string PERMISSION = 'agent-sales-report.view';

    public function up(): void
    {
        $guard = DB::table('permissions')->value('guard_name');

        if ($guard === null) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'name' => self::PERMISSION,
            'guard_name' => $guard,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', self::PERMISSION)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
