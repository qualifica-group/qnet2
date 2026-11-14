<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0396, AC-010: POST /api/opportunities/open-check shares the predicate of
 * RegistryOpenOpportunityGuard (exemptions included).
 */
uses(RefreshDatabase::class);

if (! function_exists('openCheckActor')) {
    function openCheckActor(bool $canCreate = true): User
    {
        $user = User::factory()->create();

        if ($canCreate) {
            $user->givePermissionTo(Permission::findOrCreate('opportunities.create', 'web'));
        }

        return $user;
    }
}

if (! function_exists('openCheckCategory')) {
    function openCheckCategory(bool $multipleOpen = false): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'multiple_open_opportunities' => $multipleOpen,
        ]);
    }
}

it('AC-010: returns the blocking open opportunity {id, name}', function () {
    Sanctum::actingAs(openCheckActor());
    $registry = Registry::factory()->create();
    $open = Opportunity::factory()->create(['registry_id' => $registry->id]);

    $this->postJson('/api/opportunities/open-check', ['registry_id' => $registry->id])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.blocking_opportunity.id', $open->id)
        ->assertJsonPath('data.blocking_opportunity.name', $open->name);
});

it('AC-010: returns null when the registry has no open opportunity', function () {
    Sanctum::actingAs(openCheckActor());

    $this->postJson('/api/opportunities/open-check', ['registry_id' => Registry::factory()->create()->id])
        ->assertOk()
        ->assertJsonPath('data.blocking_opportunity', null);
});

it('AC-010: returns null when the new lines sit on exempt categories', function () {
    Sanctum::actingAs(openCheckActor());
    $registry = Registry::factory()->create();
    $existingCategory = openCheckCategory(multipleOpen: true);
    $open = Opportunity::factory()->create(['registry_id' => $registry->id]);
    OpportunityProductLine::create([
        'opportunity_id' => $open->id,
        'business_function_id' => $existingCategory->business_function_id,
        'product_category_id' => $existingCategory->id,
    ]);
    $exempt = openCheckCategory(multipleOpen: true);
    $strict = openCheckCategory();

    $this->postJson('/api/opportunities/open-check', [
        'registry_id' => $registry->id,
        'product_lines' => [['product_category_id' => $exempt->id]],
    ])->assertOk()->assertJsonPath('data.blocking_opportunity', null);

    $this->postJson('/api/opportunities/open-check', [
        'registry_id' => $registry->id,
        'product_lines' => [['product_category_id' => $strict->id]],
    ])->assertOk()->assertJsonPath('data.blocking_opportunity', null);
});

it('AC-010: a strict new category beside an open strict opportunity still blocks', function () {
    Sanctum::actingAs(openCheckActor());
    $registry = Registry::factory()->create();
    $open = Opportunity::factory()->create(['registry_id' => $registry->id]);
    $strict = openCheckCategory();

    $this->postJson('/api/opportunities/open-check', [
        'registry_id' => $registry->id,
        'product_lines' => [['product_category_id' => $strict->id]],
    ])->assertOk()->assertJsonPath('data.blocking_opportunity.id', $open->id);
});

it('AC-010: without opportunities.create -> 403', function () {
    Sanctum::actingAs(openCheckActor(canCreate: false));

    $this->postJson('/api/opportunities/open-check', ['registry_id' => Registry::factory()->create()->id])
        ->assertForbidden();
});

it('AC-010: missing or unknown registry_id -> 422', function () {
    Sanctum::actingAs(openCheckActor());

    $this->postJson('/api/opportunities/open-check', [])->assertStatus(422)->assertJsonValidationErrors('registry_id');
    $this->postJson('/api/opportunities/open-check', ['registry_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('registry_id');
});
