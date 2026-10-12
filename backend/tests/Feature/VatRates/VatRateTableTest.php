<?php

use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('vatRateUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function vatRateUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("vat-rates.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("vat-rates.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// columns config
// ---------------------------------------------------------------------------

it('returns the 4 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = vatRateUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/vat-rates/columns')->assertForbidden();

    $actor = vatRateUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/vat-rates/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('vat-rates')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'rate', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterType'])->toBe('text')
        ->and($columns['rate']['filterType'])->toBe('number')
        ->and($columns['created_at']['filterType'])->toBe('date');
});

it('hides action keys the user has no permission for', function () {
    $actor = vatRateUserWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/vat-rates/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// rows shape
// ---------------------------------------------------------------------------

it('rows expose id/name/rate/created_at + per-row actions', function () {
    $actor = vatRateUserWith(['viewAny', 'view', 'update', 'delete']);
    VatRate::factory()->create(['name' => 'IVA 22%', 'rate' => 22]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/vat-rates/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'IVA 22%');

    expect($row)->not->toBeNull()
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'delete']);
});

it('422 on the values endpoint when columnId is not filterable', function () {
    $actor = vatRateUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/vat-rates/values', ['columnId' => 'id'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

it('resolves distinct names via /values', function () {
    $actor = vatRateUserWith(['viewAny']);
    VatRate::factory()->create(['name' => 'IVA 22%']);
    VatRate::factory()->create(['name' => 'IVA 10%']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/vat-rates/values', ['columnId' => 'name'])->assertOk();

    expect($response->json('data.values'))->toEqualCanonicalizing(['IVA 22%', 'IVA 10%']);
});
