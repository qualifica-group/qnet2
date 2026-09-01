<?php

use App\Models\Product;
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
// columns config — AC-022
// ---------------------------------------------------------------------------

it('returns the 6 columns in order with the declared flags, 403 without viewAny (AC-022)', function () {
    $actor = unitOfMeasureUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/units-of-measure/columns')->assertForbidden();

    $actor = unitOfMeasureUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/units-of-measure/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('units-of-measure')
        ->and($data['defaultSort'])->toBe([['columnId' => 'name', 'direction' => 'asc']])
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['searchable'])->toEqualCanonicalizing(['name', 'symbol', 'code']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'symbol', 'code', 'description', 'created_at', 'updated_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['symbol']['filterType'])->toBe('text')
        ->and($columns['code']['filterType'])->toBe('text')
        ->and($columns['description']['filterType'])->toBe('text')
        ->and($columns['created_at']['filterType'])->toBe('date');
});

it('hides action keys the user has no permission for', function () {
    $actor = unitOfMeasureUserWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/units-of-measure/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// rows shape
// ---------------------------------------------------------------------------

it('rows expose id/name/symbol/code/description + per-row actions', function () {
    $actor = unitOfMeasureUserWith(['viewAny', 'view', 'update', 'delete']);
    UnitOfMeasure::factory()->create(['name' => 'Chilogrammi', 'symbol' => 'kg', 'code' => 'kilogram']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/units-of-measure/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Chilogrammi');

    expect($row)->not->toBeNull()
        ->and($row['symbol'])->toBe('kg')
        ->and($row['code'])->toBe('kilogram')
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

// ---------------------------------------------------------------------------
// bulk-delete propagates the guard — AC-017
// ---------------------------------------------------------------------------

it('bulk-delete: a unit in use is NOT removed, the guard from the single endpoint applies (AC-017)', function () {
    $actor = unitOfMeasureUserWith(['viewAny', 'delete']);
    $inUse = UnitOfMeasure::factory()->create();
    Product::factory()->create(['unit_of_measure_id' => $inUse->id]);
    $free = UnitOfMeasure::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/units-of-measure/bulk-delete', ['ids' => [$inUse->id, $free->id]])
        ->assertOk();

    $this->assertDatabaseHas('units_of_measure', ['id' => $inUse->id]);
    $this->assertDatabaseMissing('units_of_measure', ['id' => $free->id]);
});
