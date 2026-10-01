<?php

use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\CustomFieldDefinition;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\PipelineStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Registry;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * The same authz contract already proven for `users` (AC-001/002/003) holds
 * for every OTHER resource added to config/activity-log.php: the endpoint/
 * service are fully generic (ActivityLogController/AggregatedActivityService,
 * ActivityLogRegistry), so this is a dataset over the config keys rather than
 * one test file per module. AC-004 (unknown resource -> 404) is already
 * covered generically by ActivityLogAuthorizationTest; `import-runs` is
 * deliberately excluded (spec 0034 — no activitylog on that model).
 *
 * @return array<string, class-string<Model>>
 */
if (! function_exists('activityLogModuleFixtures')) {
    function activityLogModuleFixtures(): array
    {
        return [
            'attributes' => Attribute::class,
            'business-functions' => BusinessFunction::class,
            'campaigns' => Campaign::class,
            'companies' => Company::class,
            'company-sites' => CompanySite::class,
            'contract-statuses' => ContractStatus::class,
            'contracts' => Contract::class,
            'custom-fields' => CustomFieldDefinition::class,
            'leads' => Lead::class,
            'operational-sites' => OperationalSite::class,
            'pipeline-statuses' => PipelineStatus::class,
            'product-categories' => ProductCategory::class,
            'products' => Product::class,
            'projects' => Project::class,
            'referent-types' => ReferentType::class,
            'referents' => Referent::class,
            'registries' => Registry::class,
            'roles' => Role::class,
            'sectors' => Sector::class,
            'sources' => Source::class,
            'tags' => Tag::class,
        ];
    }
}

/**
 * An actor holding the given subset of viewAny/view/viewActivity on
 * `$resource`. Self-contained (mirrors ActivityLogAuthorizationTest's own
 * `activityLogActor`, parametrized by resource) so it never collides with a
 * same-named guarded function declared by another test file.
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('activityLogModuleActor')) {
    function activityLogModuleActor(string $resource, array $abilities): User
    {
        foreach (['viewAny', 'view', 'viewActivity'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            $actor->givePermissionTo("{$resource}.{$ability}");
        }

        return $actor;
    }
}

dataset('activityLogModules', function (): array {
    $cases = [];

    foreach (activityLogModuleFixtures() as $resource => $modelClass) {
        $cases[$resource] = [$resource, $modelClass];
    }

    return $cases;
});

it('200 with the frozen envelope, given view + viewActivity (AC-001)', function (string $resource, string $modelClass) {
    $actor = activityLogModuleActor($resource, ['view', 'viewActivity']);
    /** @var Model $target */
    $target = $modelClass::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/{$resource}/{$target->getKey()}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'message', 'data' => ['items', 'next_cursor']]);
})->with('activityLogModules');

it('403 without {resource}.viewActivity (AC-002)', function (string $resource, string $modelClass) {
    $actor = activityLogModuleActor($resource, ['view']);
    /** @var Model $target */
    $target = $modelClass::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/{$resource}/{$target->getKey()}")->assertForbidden();
})->with('activityLogModules');

it('403 with {resource}.viewActivity but without {resource}.view on the record (AC-003)', function (string $resource, string $modelClass) {
    $actor = activityLogModuleActor($resource, ['viewActivity']);
    /** @var Model $target */
    $target = $modelClass::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/{$resource}/{$target->getKey()}")->assertForbidden();
})->with('activityLogModules');
