<?php

use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('unitOfMeasureUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function unitOfMeasureUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("units-of-measure.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("units-of-measure.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/units-of-measure/for-select')->assertUnauthorized();
});

it('allows actors without units-of-measure.viewAny (200 — ADR 0011 amended)', function () {
    $actor = unitOfMeasureUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/units-of-measure/for-select')->assertOk();
});

it('allows actors with units-of-measure.viewAny (200) and returns the paginated envelope', function () {
    $actor = unitOfMeasureUserWith(['viewAny']);
    UnitOfMeasure::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/units-of-measure/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// item shape + search
// ---------------------------------------------------------------------------

it('maps a unit of measure to { id, label: name, subtitle: symbol, meta: { symbol } }', function () {
    $actor = unitOfMeasureUserWith(['viewAny']);
    $target = UnitOfMeasure::factory()->create(['name' => 'Chilogrammi', 'symbol' => 'kg']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/units-of-measure/for-select?search=Chilogrammi')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Chilogrammi', 'subtitle' => 'kg'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'subtitle', 'meta'])
        ->and($item['meta'])->toMatchArray(['symbol' => 'kg']);
});

it('searches by name', function () {
    $actor = unitOfMeasureUserWith(['viewAny']);
    $match = UnitOfMeasure::factory()->create(['name' => 'Alphonse Target']);
    UnitOfMeasure::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/units-of-measure/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = unitOfMeasureUserWith(['viewAny']);
    $searchMatch = UnitOfMeasure::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = UnitOfMeasure::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/units-of-measure/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = unitOfMeasureUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/units-of-measure/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});
