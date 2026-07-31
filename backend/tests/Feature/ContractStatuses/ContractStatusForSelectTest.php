<?php

use App\Models\ContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('contractStatusForSelectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractStatusForSelectUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
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
// auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/contract-statuses/for-select')->assertUnauthorized();
});

it('allows an authenticated actor with NO module permission at all (200, AC-028)', function () {
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/contract-statuses/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// mapping, active-only, sort_order-first ordering, search, ids[] hydration
// ---------------------------------------------------------------------------

it('maps a contract status to { id, label: name, meta: { system_key } }', function () {
    $actor = contractStatusForSelectUserWith(['viewAny']);
    $target = ContractStatus::factory()->create(['name' => 'In lavorazione']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/contract-statuses/for-select?search=In lavorazione')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'In lavorazione', 'meta' => ['system_key' => null]])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'meta']);
});

it('excludes inactive statuses', function () {
    $actor = contractStatusForSelectUserWith(['viewAny']);
    $active = ContractStatus::factory()->create(['name' => 'Active One', 'is_active' => true]);
    $inactive = ContractStatus::factory()->create(['name' => 'Inactive One', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/contract-statuses/for-select')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($inactive->id);
});

it('orders by sort_order asc, not alphabetically', function () {
    $actor = contractStatusForSelectUserWith(['viewAny']);
    $zLast = ContractStatus::factory()->create(['name' => 'Zeta', 'sort_order' => 100]);
    $aFirst = ContractStatus::factory()->create(['name' => 'Alpha', 'sort_order' => 101]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/contract-statuses/for-select')->assertOk();
    $ids = collect($response->json('items'))
        ->pluck('id')
        ->filter(fn (int $id): bool => in_array($id, [$zLast->id, $aFirst->id], true))
        ->values()
        ->all();

    expect($ids)->toBe([$zLast->id, $aFirst->id]);
});

it('hydrates ids[] EVEN when the status is inactive', function () {
    $actor = contractStatusForSelectUserWith(['viewAny']);
    $inactive = ContractStatus::factory()->create(['name' => 'Deactivated One', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/contract-statuses/for-select?ids[]={$inactive->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($inactive->id);
});

it('an inactive status is absent from the plain list but present when explicitly requested via ids[] (BR-5)', function () {
    $actor = contractStatusForSelectUserWith(['viewAny']);
    $inactive = ContractStatus::factory()->create(['name' => 'Deactivated Edit-Mode', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $plainList = $this->getJson('/api/contract-statuses/for-select')->assertOk();
    expect(collect($plainList->json('items'))->pluck('id'))->not->toContain($inactive->id);

    $hydrated = $this->getJson("/api/contract-statuses/for-select?ids[]={$inactive->id}")->assertOk();
    expect(collect($hydrated->json('items'))->pluck('id'))->toContain($inactive->id);
});

it('rejects a limit above 100 (422)', function () {
    $actor = contractStatusForSelectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/contract-statuses/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});
