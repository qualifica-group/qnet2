<?php

use App\Models\ContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('contractStatusTableUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractStatusTableUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("contract-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("contract-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/contract-statuses/columns: 403 without viewAny, 200 with the declared columns', function () {
    $actor = contractStatusTableUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/contract-statuses/columns')->assertForbidden();

    $actor = contractStatusTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/contract-statuses/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('contract-statuses')
        ->and($data['defaultSort'])->toBe([['columnId' => 'sort_order', 'direction' => 'asc']]);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'description', 'color', 'sort_order', 'is_active', 'is_default', 'group', 'created_at']);
});

// ---------------------------------------------------------------------------
// rows: no `delete` action on a system row, present on a custom row
// ---------------------------------------------------------------------------

it('rows: delete is absent on system rows and present on custom rows', function () {
    $actor = contractStatusTableUserWith(['viewAny', 'view', 'update', 'delete']);
    ContractStatus::factory()->create(['name' => 'Personalizzata', 'color' => 'red', 'sort_order' => 5]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/contract-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $items = collect($response->json('items'));

    $customRow = $items->firstWhere('name', 'Personalizzata');
    expect($customRow)->not->toBeNull()
        ->and($customRow['actions'])->toContain('delete');

    foreach (['new', 'suspended', 'cancelled', 'terminated'] as $systemKey) {
        $systemRow = $items->firstWhere('system_key', $systemKey);
        expect($systemRow)->not->toBeNull()
            ->and($systemRow['actions'])->not->toContain('delete');
    }
});

it('rows: delete is absent everywhere without contract-statuses.delete', function () {
    $actor = contractStatusTableUserWith(['viewAny', 'view', 'update']);
    ContractStatus::factory()->create(['name' => 'Personalizzata']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/contract-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    foreach ($response->json('items') as $row) {
        expect($row['actions'])->not->toContain('delete');
    }
});
