<?php

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
 * Spec 0110 AC-030/AC-031 read through spec 0111 AC-016: GET
 * /api/users/for-select keeps the ADDITIVE `competence_category_ids[]`
 * filter — the picker asks for the operators competent for a record's
 * required categories, now evaluated PER ROW (D-2), wildcards included
 * (INV-4b). Absent, the endpoint answers exactly as it did before the
 * feature; combined with `operational_site_id` the two filters AND (INV-5:
 * competence only ever narrows).
 *
 * Distinct file from UserForSelectTest / UserForSelectSiteFilterTest (their
 * coverage is untouched).
 */
if (! function_exists('competenceForSelectActor')) {
    function competenceForSelectActor(): User
    {
        Permission::findOrCreate('users.viewAny');
        $actor = User::factory()->create();
        $actor->givePermissionTo('users.viewAny');

        return $actor;
    }
}

if (! function_exists('competenceForSelectUser')) {
    /**
     * A user whose employment profile carries one competence row per
     * category, all paired with $function (spec 0111 D-2), optionally
     * sitting at $site. A user with no row at all is the wildcard of INV-4b
     * and is built without this helper.
     *
     * @param  array<int, ProductCategory>  $categories
     */
    function competenceForSelectUser(BusinessFunction $function, array $categories, ?OperationalSite $site = null): User
    {
        $user = User::factory()->create();

        $factory = EmploymentProfile::factory()->for($user)->competentIn($function, ...$categories);

        if ($site !== null) {
            $factory = $factory->physicalSite($site);
        }

        $factory->create();

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-030 — competence_category_ids narrows to the competent operators.
// ---------------------------------------------------------------------------

it('0111 AC-016: competence_category_ids answers only the competent operators plus the wildcards, and the total reflects the filter', function () {
    $actor = competenceForSelectActor();
    $function = BusinessFunction::factory()->create();
    $otherFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $otherCategory = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $competent = competenceForSelectUser($function, [$category]);
    $wrongFunction = competenceForSelectUser($otherFunction, [$category]);
    $wrongCategory = competenceForSelectUser($function, [$otherCategory]);
    $wildcard = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/for-select?competence_category_ids[]={$category->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($competent->id);
    // The actor has no employment profile either: wildcards stay candidates.
    expect($ids)->toContain($wildcard->id, $actor->id);
    expect($ids)->not->toContain($wrongFunction->id);
    expect($ids)->not->toContain($wrongCategory->id);
    // 5 users exist, 3 survive the filter: the total is filtered, not the
    // full table count.
    expect($response->json('pagination.total'))->toBe(3);
});

it('0111 AC-016: an operator competent through a SECOND row on another function is answered too', function () {
    $actor = competenceForSelectActor();
    $firstFunction = BusinessFunction::factory()->create();
    $secondFunction = BusinessFunction::factory()->create();
    $firstCategory = ProductCategory::factory()->create(['business_function_id' => $firstFunction->id]);
    $secondCategory = ProductCategory::factory()->create(['business_function_id' => $secondFunction->id]);

    $user = User::factory()->create();
    EmploymentProfile::factory()
        ->for($user)
        ->competentIn($firstFunction, $firstCategory)
        ->competentIn($secondFunction, $secondCategory)
        ->create();
    $onlyFirst = competenceForSelectUser($firstFunction, [$firstCategory]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/for-select?competence_category_ids[]={$secondCategory->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($user->id, $actor->id);
    expect($ids)->not->toContain($onlyFirst->id);
    expect($response->json('pagination.total'))->toBe(2);
});

it('0110 AC-030: an unknown category id is a 422, never a silently empty picker', function () {
    Sanctum::actingAs(competenceForSelectActor());

    $this->getJson('/api/users/for-select?competence_category_ids[]=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('competence_category_ids.0');
});

// ---------------------------------------------------------------------------
// AC-031 — retro-compatibility, and AND with operational_site_id (INV-5).
// ---------------------------------------------------------------------------

it('0110 AC-031: without competence_category_ids every user is answered, as before the feature', function () {
    $actor = competenceForSelectActor();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $otherCategory = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $competent = competenceForSelectUser($function, [$category]);
    $incompetent = competenceForSelectUser($function, [$otherCategory]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select')->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($competent->id, $incompetent->id, $actor->id);
    expect($response->json('pagination.total'))->toBe(3);
});

it('0110 AC-031: combined with operational_site_id the two filters AND', function () {
    $actor = competenceForSelectActor();
    $function = BusinessFunction::factory()->create();
    $otherFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $siteA = OperationalSite::factory()->withAddress()->create();
    $siteB = OperationalSite::factory()->withAddress()->create();

    $competentAtSiteA = competenceForSelectUser($function, [$category], $siteA);
    $incompetentAtSiteA = competenceForSelectUser($otherFunction, [$category], $siteA);
    $competentAtSiteB = competenceForSelectUser($function, [$category], $siteB);
    Sanctum::actingAs($actor);

    $response = $this->getJson(
        "/api/users/for-select?operational_site_id={$siteA->id}&competence_category_ids[]={$category->id}"
    )->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($competentAtSiteA->id);
    expect($ids)->not->toContain($incompetentAtSiteA->id);
    expect($ids)->not->toContain($competentAtSiteB->id);
    // The actor is a wildcard, but has no Sede: the site filter still
    // excludes them — competence narrows, it never re-admits.
    expect($ids)->not->toContain($actor->id);
    expect($response->json('pagination.total'))->toBe(1);
});

it('0110 AC-031: ids[] hydration still bypasses the competence filter', function () {
    $actor = competenceForSelectActor();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $otherCategory = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $incompetent = competenceForSelectUser($function, [$otherCategory]);
    Sanctum::actingAs($actor);

    $response = $this->getJson(
        "/api/users/for-select?competence_category_ids[]={$category->id}&ids[]={$incompetent->id}"
    )->assertOk();

    // An already-assigned operator stays visible in the field showing them,
    // even once they stop matching the filter — and does not inflate the total.
    expect(collect($response->json('items'))->pluck('id'))->toContain($incompetent->id);
    expect($response->json('pagination.total'))->toBe(1);
});
