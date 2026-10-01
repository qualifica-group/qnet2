<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\Source;
use App\Models\User;
use App\RequestManagement\RequestModule;
use Spatie\Permission\Models\Permission;

if (! function_exists('requestManagementUserWith')) {
    /**
     * Canonicalised here (engineering.md §1.2): the former local copies
     * disagreed on the ability catalogue itself — four files never created
     * `request-management.transferContact`, one never created
     * `create`/`delete`/`import` — so whichever file's copy loaded first
     * decided, for the WHOLE run, which abilities the OTHER files' actors
     * could even be granted. The set below is the union of every ability
     * any caller in this directory requests.
     *
     * @param  array<int, string>  $abilities
     */
    function requestManagementUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'transferContact'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('requestManagementCreatorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementCreatorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'export', 'viewActivity', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('aSourceId')) {
    /** The Fonte every successful create must carry: mandatory since the user directive 2026-07-29. */
    function aSourceId(): int
    {
        return Source::factory()->create()->id;
    }
}

if (! function_exists('oneProductLine')) {
    /**
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    function oneProductLine(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]];
    }
}

if (! function_exists('requestReportPermission')) {
    /**
     * The permission a report/dashboard test actor is given for `$ability`:
     * `statistics` is the "Statistiche Gestione Richieste" module's own
     * permission (spec 0185), anything else a `request-management.*` ability.
     */
    function requestReportPermission(string $ability): string
    {
        return $ability === 'statistics' ? RequestModule::STATISTICS_PERMISSION : "request-management.{$ability}";
    }
}
