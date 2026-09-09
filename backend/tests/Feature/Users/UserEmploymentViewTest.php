<?php

use App\Http\Resources\EmploymentResource;
use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * GET /api/users/{user} — the employment tree's multi-site membership (spec
 * 0103) and its competence rows (spec 0111). Split out of UserCrudTest.php to
 * keep that file under the 500-line hard limit (engineering.md §6).
 */
if (! function_exists('userWithUserAbilities')) {
    function userWithUserAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

/**
 * Spec 0111 AC-010: the competence rows carry the shape every other owner of
 * the collection emits, and the four keys they replaced are gone for good.
 */
it('view: employment carries the competence rows, not the old business-function/category keys (0111 AC-010)', function () {
    $actor = userWithUserAbilities(['view']);
    $function = BusinessFunction::factory()->create(['name' => 'Engineering']);
    $category = ProductCategory::factory()->create(['name' => 'Fibra', 'business_function_id' => $function->id]);
    $target = User::factory()->create();
    $profile = EmploymentProfile::factory()->create(['user_id' => $target->id, 'job_description' => 'Backend engineer']);
    $profile->productLines()->create([
        'business_function_id' => $function->id,
        'product_category_id' => $category->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.employment.job_description', 'Backend engineer')
        ->assertJsonCount(1, 'data.employment.product_lines')
        ->assertJsonPath('data.employment.product_lines.0.business_function', ['id' => $function->id, 'name' => 'Engineering'])
        ->assertJsonPath('data.employment.product_lines.0.product_category', ['id' => $category->id, 'name' => 'Fibra'])
        ->assertJsonMissingPath('data.employment.business_function_id')
        ->assertJsonMissingPath('data.employment.business_function')
        ->assertJsonMissingPath('data.employment.product_category_ids')
        ->assertJsonMissingPath('data.employment.product_categories');

    expect($response->json('data.employment.product_lines.0.id'))->toBe($profile->productLines()->value('id'));
});

/**
 * The other half of AC-010: without the relation loaded the key is omitted
 * entirely (same whenLoaded discipline as the site references below), which
 * is what keeps a resource rendered outside UserService::loadProfileTree()
 * from lazy-loading.
 */
it('view: employment omits product_lines when the relation is not eager-loaded (0111 AC-010)', function () {
    $function = BusinessFunction::factory()->create();
    $profile = EmploymentProfile::factory()->create();
    $profile->productLines()->create([
        'business_function_id' => $function->id,
        'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $function->id])->id,
    ]);

    $payload = EmploymentResource::make($profile->fresh())->resolve();

    expect($payload)->not->toHaveKey('product_lines');
});

/**
 * AC-009: the response carries the two site ids plus their two reference
 * objects, and the old single-site keys are gone for good.
 */
it('view: employment carries the physical/remote Sede ids and references, not the old operational_site key (AC-009)', function () {
    $actor = userWithUserAbilities(['view']);
    $target = User::factory()->create();
    $physical = OperationalSite::factory()->withAddress()->create();
    $remote = OperationalSite::factory()->withAddress()->create();
    EmploymentProfile::factory()
        ->physicalSite($physical)
        ->remoteSites($remote)
        ->create(['user_id' => $target->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.employment.primary_operational_site_id', $physical->id)
        ->assertJsonPath('data.employment.remote_operational_site_ids', [$remote->id])
        ->assertJsonPath('data.employment.primary_operational_site.id', $physical->id)
        ->assertJsonCount(1, 'data.employment.remote_operational_sites')
        ->assertJsonPath('data.employment.remote_operational_sites.0.id', $remote->id)
        ->assertJsonMissingPath('data.employment.operational_site_id')
        ->assertJsonMissingPath('data.employment.operational_site');

    expect($response->json('data.employment.primary_operational_site.label'))->not->toBe('');
});

it('view: employment omits the reference objects when no Sede is a member', function () {
    $actor = userWithUserAbilities(['view']);
    $target = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.employment.primary_operational_site_id', null)
        ->assertJsonPath('data.employment.remote_operational_site_ids', [])
        ->assertJsonMissingPath('data.employment.primary_operational_site')
        ->assertJsonCount(0, 'data.employment.remote_operational_sites');
});
