<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

if (! function_exists('statsDomains')) {
    /**
     * The domains in scope (spec 0026). Declared here as a literal — NOT read from
     * config at collection time — and cross-checked against config/stats.php by
     * the registration test in StatsEndpointTest, so a domain added/removed in config without a
     * test is caught.
     *
     * `projects` is now IN scope (explicit requirement change on top of spec 0026,
     * whose <scope> excluded it): it has a definition like any other module and its
     * legacy GET /projects/summary keeps working unchanged.
     *
     * `import-runs` joined the panel with spec 0034 (dedicated lead-import
     * module): its StatsDefinition is scoped to the actor's OWN runs (unlike
     * every other domain's global counts), verified separately in
     * ImportRunsStatsTest.
     *
     * `opportunities` joined the panel with spec 0040, same registry pattern.
     *
     * `tasks` joined with spec 0147 (requirement change, declared): scoped to the
     * actor's visible root tasks, values verified in TasksStatsTest.
     *
     * `quotes` joined with spec 0151 (dashboard, D-8, requirement change,
     * declared): global counts, same registry pattern as `opportunities`.
     *
     * @return array<int, string>
     */
    function statsDomains(): array
    {
        return [
            'registries',
            'referents',
            'companies',
            'operational-sites',
            'company-sites',
            'products',
            'product-categories',
            'projects',
            'campaigns',
            'leads',
            'business-functions',
            'users',
            'import-runs',
            'opportunities',
            'tasks',
            'quotes',
        ];
    }
}

if (! function_exists('statsPermissionForDomain')) {
    /**
     * The permission gating a domain's stats panel. Every module uses its own
     * `{domain}.viewAny`, EXCEPT `import-runs`: its dedicated permission set was
     * removed (2026-07-17) and the module rides the lead module's `leads.import`
     * (ImportRunPolicy::viewAny).
     */
    function statsPermissionForDomain(string $domain): string
    {
        return $domain === 'import-runs' ? 'leads.import' : "{$domain}.viewAny";
    }
}

if (! function_exists('statsUserWith')) {
    /**
     * A user granted each domain's stats permission (see statsPermissionForDomain).
     * Every permission is created first (idempotent), so a user granted none is
     * genuinely unauthorized rather than merely missing a permission row.
     *
     * @param  array<int, string>  $domains
     */
    function statsUserWith(array $domains): User
    {
        foreach (statsDomains() as $domain) {
            Permission::findOrCreate(statsPermissionForDomain($domain));
        }

        $user = User::factory()->create();

        foreach ($domains as $domain) {
            $user->givePermissionTo(statsPermissionForDomain($domain));
        }

        return $user;
    }
}

if (! function_exists('statsWidgets')) {
    /**
     * The widget list of a domain, fetched as an actor authorized on it.
     *
     * @return array<int, array<string, mixed>>
     */
    function statsWidgets(string $domain): array
    {
        Sanctum::actingAs(statsUserWith([$domain]));

        return test()->getJson("/api/stats/{$domain}")->assertOk()->json('data.widgets');
    }
}

if (! function_exists('statsWidget')) {
    /**
     * @param  array<int, array<string, mixed>>  $widgets
     * @return array<string, mixed>
     */
    function statsWidget(array $widgets, string $key): array
    {
        $widget = collect($widgets)->firstWhere('key', $key);

        expect($widget)->not->toBeNull("widget [{$key}] is missing");

        return $widget;
    }
}
