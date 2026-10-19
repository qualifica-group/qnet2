<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('businessFunctionActiveUserWith')) {
    /**
     * @param  array<int, string>  $abilities  business-functions abilities
     */
    function businessFunctionActiveUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(array_map(static fn (string $ability): string => "business-functions.{$ability}", $abilities));

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-001 / AC-002 — own flag on write and read
// ---------------------------------------------------------------------------

it('AC-001: a function created without is_active is active', function () {
    Sanctum::actingAs(businessFunctionActiveUserWith(['create']));

    $this->postJson('/api/business-functions', ['name' => 'Formazione'])
        ->assertCreated()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.inactive_ancestor', null);

    expect(BusinessFunction::firstWhere('name', 'Formazione')->is_active)->toBeTrue();
});

it('AC-001: PATCH is_active=false persists the own flag and leaves the children untouched', function () {
    $root = BusinessFunction::factory()->create();
    $child = BusinessFunction::factory()->childOf($root)->create();
    Sanctum::actingAs(businessFunctionActiveUserWith(['update']));

    $this->patchJson("/api/business-functions/{$root->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($root->fresh()->is_active)->toBeFalse()
        ->and($child->fresh()->is_active)->toBeTrue();
});

it('AC-001: a non-boolean is_active is rejected', function () {
    $function = BusinessFunction::factory()->create();
    Sanctum::actingAs(businessFunctionActiveUserWith(['update']));

    $this->patchJson("/api/business-functions/{$function->id}", ['is_active' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_active');
});

it('AC-002: a descendant of an inactive function names its closest inactive ancestor', function () {
    $root = BusinessFunction::factory()->inactive()->create(['name' => 'Root']);
    $middle = BusinessFunction::factory()->childOf($root)->inactive()->create(['name' => 'Middle']);
    $leaf = BusinessFunction::factory()->childOf($middle)->create();
    Sanctum::actingAs(businessFunctionActiveUserWith(['view']));

    $this->getJson("/api/business-functions/{$leaf->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.inactive_ancestor', ['id' => $middle->id, 'name' => 'Middle']);

    $this->getJson("/api/business-functions/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.inactive_ancestor', null);
});

// ---------------------------------------------------------------------------
// AC-003 — for-select hides the inactive branch, include_inactive and ids[] see it
// ---------------------------------------------------------------------------

it('AC-003: for-select leaves out an inactive function and its descendants', function () {
    $active = BusinessFunction::factory()->create(['name' => 'Active']);
    $inactive = BusinessFunction::factory()->inactive()->create(['name' => 'Inactive']);
    $child = BusinessFunction::factory()->childOf($inactive)->create(['name' => 'Inactive child']);
    Sanctum::actingAs(User::factory()->create());

    $default = $this->getJson('/api/business-functions/for-select')->assertOk();
    $withInactive = $this->getJson('/api/business-functions/for-select?include_inactive=1')->assertOk();
    $hydrated = $this->getJson("/api/business-functions/for-select?ids[]={$child->id}")->assertOk();

    expect(collect($default->json('items'))->pluck('id')->all())->toBe([$active->id])
        ->and($default->json('pagination.total'))->toBe(1)
        ->and(collect($withInactive->json('items'))->pluck('id')->all())->toEqualCanonicalizing([$active->id, $inactive->id, $child->id])
        ->and(collect($hydrated->json('items'))->pluck('id')->all())->toContain($child->id);
});

// ---------------------------------------------------------------------------
// AC-009 — grid column and filter, advanced filters see the inactive ones
// ---------------------------------------------------------------------------

it('AC-009: the grid exposes the own flag, filterable', function () {
    BusinessFunction::factory()->inactive()->create(['name' => 'Off']);
    Sanctum::actingAs(businessFunctionActiveUserWith(['viewAny']));

    $columns = collect($this->getJson('/api/tables/business-functions/columns')->assertOk()->json('data.columns'));
    $rows = $this->postJson('/api/tables/business-functions/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');

    expect($columns->firstWhere('id', 'is_active'))->toMatchArray(['type' => 'boolean', 'filterable' => true, 'sortable' => true])
        ->and(collect($rows)->firstWhere('name', 'Off')['is_active'])->toBeFalse();
});
