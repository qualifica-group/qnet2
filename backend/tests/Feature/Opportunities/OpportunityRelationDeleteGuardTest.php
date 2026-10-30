<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * BR-3 (spec 0040): none of the entities an Opportunity references may be
 * deleted while that Opportunity exists (AC-020..024). AC-025 (no false
 * positive on an unreferenced row / manager pivot NOT blocking) is exercised
 * inline here plus by the pre-existing delete suites of the other modules,
 * proven green alongside this file. User directive 2026-07-17: the guards on
 * Company/CompanySite/OperationalSite (referenced via the now-removed
 * `company_id`/`company_site_id`/`operational_site_id`) are REMOVED entirely
 * — those 3 entities no longer have any Opportunity-driven delete
 * restriction (OperationalSite keeps its own leads-driven guard, untouched).
 *
 * Spec 0056 (2026-07-23) REINTRODUCES `operational_site_id` alone, but with a
 * DIFFERENT FK behaviour than the rest of this file's restrictOnDelete guards
 * (AC-008): `nullOnDelete`, a deliberate deviation covered below — deleting a
 * referenced Sede operativa never 409s, the Opportunity survives with the
 * field cleared.
 */
uses(RefreshDatabase::class);

if (! function_exists('grantDeleteAbility')) {
    function grantDeleteAbility(string $resource): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }
        $actor = User::factory()->create();
        $actor->givePermissionTo("{$resource}.delete");

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-020 — Registry
// ---------------------------------------------------------------------------

it('a registry referenced by an opportunity cannot be deleted: 409, not deleted (AC-020)', function () {
    $actor = grantDeleteAbility('registries');
    $registry = Registry::factory()->create();
    Opportunity::factory()->create(['registry_id' => $registry->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/registries/{$registry->id}")->assertStatus(409);
    $this->assertDatabaseHas('registries', ['id' => $registry->id]);
});

// ---------------------------------------------------------------------------
// AC-021 — BusinessFunction
// ---------------------------------------------------------------------------

it('a business function referenced by an opportunity (via a product line) cannot be deleted: 409, not deleted (AC-021, amendment rev.3)', function () {
    $actor = grantDeleteAbility('business-functions');
    $businessFunction = BusinessFunction::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create([
        'business_function_id' => $businessFunction->id,
        'product_category_id' => ProductCategory::factory()->create()->id,
    ]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/business-functions/{$businessFunction->id}")->assertStatus(409);
    $this->assertDatabaseHas('business_functions', ['id' => $businessFunction->id]);
});

// ---------------------------------------------------------------------------
// AC-022 — Referent, as referent / commercial / reporter
// ---------------------------------------------------------------------------

it('a referent used as an opportunity\'s own referent cannot be deleted: 409 (AC-022)', function () {
    $actor = grantDeleteAbility('referents');
    $referent = Referent::factory()->create();
    Opportunity::factory()->create(['referent_id' => $referent->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/referents/{$referent->id}")->assertStatus(409);
    $this->assertDatabaseHas('referents', ['id' => $referent->id]);
});

it('a referent used as an opportunity\'s commercial cannot be deleted: 409 (AC-022)', function () {
    $actor = grantDeleteAbility('referents');
    $referent = Referent::factory()->create();
    Opportunity::factory()->create(['commercial_id' => $referent->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/referents/{$referent->id}")->assertStatus(409);
    $this->assertDatabaseHas('referents', ['id' => $referent->id]);
});

it('a referent used as an opportunity\'s reporter cannot be deleted: 409 (AC-022)', function () {
    $actor = grantDeleteAbility('referents');
    $referent = Referent::factory()->create();
    Opportunity::factory()->create(['reporter_id' => $referent->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/referents/{$referent->id}")->assertStatus(409);
    $this->assertDatabaseHas('referents', ['id' => $referent->id]);
});

// ---------------------------------------------------------------------------
// AC-023 — User (supervisor), Source, ProductCategory
// ---------------------------------------------------------------------------

it('a user referenced as an opportunity supervisor cannot be deleted: 409 (AC-023)', function () {
    $actor = grantDeleteAbility('users');
    $supervisor = User::factory()->create();
    Opportunity::factory()->create(['supervisor_id' => $supervisor->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/users/{$supervisor->id}")->assertStatus(409);
    $this->assertDatabaseHas('users', ['id' => $supervisor->id]);
});

// REQUIREMENT CHANGED: spec 0332 — the Fonte is held by the offer, which now guards the deletion.
it('a source referenced by a quote cannot be deleted: 409 (AC-023)', function () {
    $actor = grantDeleteAbility('sources');
    $source = Source::factory()->create();
    Quote::factory()->create(['source_id' => $source->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/sources/{$source->id}")->assertStatus(409);
    $this->assertDatabaseHas('sources', ['id' => $source->id]);
});

it('a product category referenced by an opportunity (via a product line) cannot be deleted: 409 (AC-023, amendment rev.3)', function () {
    $actor = grantDeleteAbility('product-categories');
    $productCategory = ProductCategory::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $productCategory->id,
    ]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/product-categories/{$productCategory->id}")->assertStatus(409);
    $this->assertDatabaseHas('product_categories', ['id' => $productCategory->id]);
});

// ---------------------------------------------------------------------------
// AC-024 — Lead (linked opportunity) / manager pivot does NOT block
// ---------------------------------------------------------------------------

it('a lead with a linked opportunity cannot be deleted: 409 (AC-024)', function () {
    $actor = grantDeleteAbility('leads');
    $lead = Lead::factory()->create();
    Opportunity::factory()->create(['lead_id' => $lead->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/leads/{$lead->id}")->assertStatus(409);
    $this->assertDatabaseHas('leads', ['id' => $lead->id]);
});

it('a user present ONLY as an opportunity manager (not supervisor) does NOT block deletion (AC-024)', function () {
    $actor = grantDeleteAbility('users');
    $manager = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$manager->id => ['position' => 1]]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/users/{$manager->id}")->assertNoContent();
    $this->assertDatabaseMissing('users', ['id' => $manager->id]);
    $this->assertDatabaseMissing('opportunity_user', ['user_id' => $manager->id]);
});

// ---------------------------------------------------------------------------
// AC-025 — no false positive: an unreferenced row of each of the 10 modules
// still deletes cleanly (204)
// ---------------------------------------------------------------------------

it('an unreferenced registry still deletes cleanly (AC-025)', function () {
    $actor = grantDeleteAbility('registries');
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/registries/{$registry->id}")->assertNoContent();
});

it('an unreferenced company still deletes cleanly (AC-025)', function () {
    $actor = grantDeleteAbility('companies');
    $company = Company::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/companies/{$company->id}")->assertNoContent();
});

it('an unreferenced company site still deletes cleanly (AC-025)', function () {
    $actor = grantDeleteAbility('company-sites');
    $companySite = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/company-sites/{$companySite->id}")->assertNoContent();
});

it('an unreferenced operational site still deletes cleanly (AC-025)', function () {
    $actor = grantDeleteAbility('operational-sites');
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/operational-sites/{$site->id}")->assertNoContent();
});

// ---------------------------------------------------------------------------
// AC-008 (spec 0056) — nullOnDelete deviation: deleting a Sede operativa
// referenced by an opportunity NEVER 409s; the opportunity survives, field
// cleared
// ---------------------------------------------------------------------------

it('deleting an operational site referenced by an opportunity: no 409, the opportunity survives with the field cleared (AC-008)', function () {
    $actor = grantDeleteAbility('operational-sites');
    $site = OperationalSite::factory()->withAddress()->create();
    $opportunity = Opportunity::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/operational-sites/{$site->id}")->assertNoContent();

    $this->assertDatabaseMissing('operational_sites', ['id' => $site->id]);
    $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'operational_site_id' => null]);
});

it('an unreferenced business function still deletes cleanly (AC-025)', function () {
    $actor = grantDeleteAbility('business-functions');
    $businessFunction = BusinessFunction::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/business-functions/{$businessFunction->id}")->assertNoContent();
});

it('an unreferenced referent still deletes cleanly (AC-025)', function () {
    $actor = grantDeleteAbility('referents');
    $referent = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/referents/{$referent->id}")->assertNoContent();
});

it('an unreferenced source still deletes cleanly (AC-025)', function () {
    $actor = grantDeleteAbility('sources');
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/sources/{$source->id}")->assertNoContent();
});

it('an unreferenced product category still deletes cleanly (AC-025)', function () {
    $actor = grantDeleteAbility('product-categories');
    $productCategory = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/product-categories/{$productCategory->id}")->assertNoContent();
});

it('an unreferenced user still deletes cleanly (AC-025)', function () {
    $actor = grantDeleteAbility('users');
    $user = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/users/{$user->id}")->assertNoContent();
});

it('an unreferenced lead still deletes cleanly (AC-025)', function () {
    $actor = grantDeleteAbility('leads');
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/leads/{$lead->id}")->assertNoContent();
});
