<?php

use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('sourceUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function sourceUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("sources.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("sources.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-005 — columns config
// ---------------------------------------------------------------------------

it('returns the 3 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = sourceUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/sources/columns')->assertForbidden();

    $actor = sourceUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/sources/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('sources')
        ->and($data['defaultSort'])->toBe([['columnId' => 'name', 'direction' => 'asc']])
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterType'])->toBe('text')
        ->and($columns['created_at']['filterType'])->toBe('date');
});

it('hides action keys the user has no permission for', function () {
    $actor = sourceUserWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/sources/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// AC-005 — rows shape
// ---------------------------------------------------------------------------

it('rows expose id/name/created_at + per-row actions', function () {
    $actor = sourceUserWith(['viewAny', 'view', 'update', 'delete']);
    Source::factory()->create(['name' => 'Website']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/sources/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Website');

    expect($row)->not->toBeNull()
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'delete']);
});

it('422 on the values endpoint when columnId is not filterable', function () {
    $actor = sourceUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/sources/values', ['columnId' => 'id'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

it('resolves distinct names via /values', function () {
    $actor = sourceUserWith(['viewAny']);
    Source::factory()->create(['name' => 'Referral']);
    Source::factory()->create(['name' => 'Partner']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/sources/values', ['columnId' => 'name'])->assertOk();

    expect($response->json('data.values'))->toEqualCanonicalizing(['Referral', 'Partner']);
});
