<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0365 D-11: the "Report Vendite Agenti" joins the `sales` menu app
 * (spec 0317) already created on this installation. A missing workspace is
 * left alone: the catalogue seeds it with the key when it is created.
 */
return new class extends Migration
{
    private const string WORKSPACE_SLUG = 'sales';

    private const string NAVIGATION_KEY = 'agent-sales-report';

    public function up(): void
    {
        $workspaceId = DB::table('workspaces')->where('slug', self::WORKSPACE_SLUG)->value('id');

        if ($workspaceId === null) {
            return;
        }

        DB::table('workspace_navigation_items')->insertOrIgnore([
            'workspace_id' => $workspaceId,
            'navigation_key' => self::NAVIGATION_KEY,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('workspace_navigation_items')->where('navigation_key', self::NAVIGATION_KEY)
            ->whereIn('workspace_id', DB::table('workspaces')->where('slug', self::WORKSPACE_SLUG)->select('id'))
            ->delete();
    }
};
