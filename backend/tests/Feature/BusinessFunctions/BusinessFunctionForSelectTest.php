<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('businessFunctionUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function businessFunctionUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-008 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/business-functions/for-select')->assertUnauthorized();
});

it('allows actors without business-functions.viewAny (200 — ADR 0011 amended)', function () {
    $actor = businessFunctionUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/business-functions/for-select')->assertOk();
});

it('allows actors with business-functions.viewAny (200) and returns the paginated envelope', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    BusinessFunction::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/business-functions/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ])
        ->assertJsonPath('export_link', null);
});

// ---------------------------------------------------------------------------
// AC-008 — item shape
// ---------------------------------------------------------------------------

it('maps a business function to { id, label: name }', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $target = BusinessFunction::factory()->create(['name' => 'Human Resources']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/business-functions/for-select?search=Human Resources')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Human Resources'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

// ---------------------------------------------------------------------------
// AC-008 — search
// ---------------------------------------------------------------------------

it('searches by name', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $match = BusinessFunction::factory()->create(['name' => 'Alphonse Target']);
    BusinessFunction::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/business-functions/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-008 — ids[] hydration
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $searchMatch = BusinessFunction::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = BusinessFunction::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/business-functions/for-select?search=Zephyr&ids[]={$selected->id}")
        ->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-008 — pagination
// ---------------------------------------------------------------------------

it('respects offset/limit and reports total + total_pages', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    BusinessFunction::factory()->count(29)->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/business-functions/for-select?offset=0&limit=10')
        ->assertOk()
        ->assertJsonPath('pagination.total', 29)
        ->assertJsonPath('pagination.offset', 0)
        ->assertJsonPath('pagination.limit', 10)
        ->assertJsonPath('pagination.total_pages', 3);

    expect($response->json('items'))->toHaveCount(10);
});

// ---------------------------------------------------------------------------
// AC-008 — validation bounds
// ---------------------------------------------------------------------------

it('rejects a limit above 100 (422)', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/business-functions/for-select?limit=101')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});

// ---------------------------------------------------------------------------
// spec 0010 REV — exclude_descendants_of (edit-mode parent picker)
// ---------------------------------------------------------------------------

it('exclude_descendants_of excludes the node itself and every descendant', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $root = BusinessFunction::factory()->create(['name' => 'Root']);
    $child = BusinessFunction::factory()->childOf($root)->create(['name' => 'Child']);
    $grandchild = BusinessFunction::factory()->childOf($child)->create(['name' => 'Grandchild']);
    $unrelated = BusinessFunction::factory()->create(['name' => 'Unrelated']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/business-functions/for-select?exclude_descendants_of={$root->id}&limit=100")
        ->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids)->not->toContain($root->id)
        ->and($ids)->not->toContain($child->id)
        ->and($ids)->not->toContain($grandchild->id)
        ->and($ids)->toContain($unrelated->id);
});

it('422 when exclude_descendants_of does not reference an existing function', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/business-functions/for-select?exclude_descendants_of=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('exclude_descendants_of');
});
