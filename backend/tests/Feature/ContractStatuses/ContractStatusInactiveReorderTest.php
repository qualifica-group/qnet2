<?php

use App\Models\ContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Regression: reordering `contract-statuses` with a deactivated custom row
|--------------------------------------------------------------------------
|
| The bug this file pins down was live in production. The shared reorder
| sheet feeds itself from `contract-statuses/for-select`, which filtered
| `is_active = true`, while StatusOrderManager::assertValidReorderSet()
| validates `ordered_ids` against EVERY custom row regardless of `is_active`.
| Deactivating a single custom status was therefore enough to make every
| drag answer 422 "none missing", and the module could no longer be
| reordered at all.
|
| The fix widened the READ (`include_inactive` on the for-select) and left
| the GUARD alone. That distinction is what these tests are built to hold:
| the last two assert that omitting the deactivated row is STILL a 422 and
| that the system rows are STILL excluded, so a future change that "fixed"
| the symptom by loosening the guard would fail here rather than pass.
|
| Written as behaviour, never as implementation: no assertion below names
| `include_inactive` as a service flag, only as the query parameter a client
| actually sends.
*/

if (! function_exists('contractStatusInactiveReorderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractStatusInactiveReorderUserWith(array $abilities): User
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

/**
 * The three custom rows the create-table migration seeds, alongside the five
 * system ones. Read back rather than created: they are what a real install
 * has, and they are the exact set `reorder` demands.
 *
 * @return Collection<int, ContractStatus>
 */
function seededCustomContractStatuses(): Collection
{
    return ContractStatus::query()->whereNull('system_key')->orderBy('sort_order')->get();
}

// ---------------------------------------------------------------------------
// 1 + 2 — the read, both halves
// ---------------------------------------------------------------------------

it('for-select still hides a deactivated custom status when the parameter is absent', function () {
    // The half that protects the ORDINARY consumers: a status picker must
    // not start offering deactivated rows because the reorder sheet needed
    // them. Without this, turning the flag permanently on would go unnoticed.
    $actor = contractStatusInactiveReorderUserWith(['viewAny']);
    $custom = seededCustomContractStatuses()->first();
    $custom->update(['is_active' => false]);
    Sanctum::actingAs($actor);

    $ids = collect($this->getJson('/api/contract-statuses/for-select')->assertOk()->json('items'))->pluck('id');

    expect($ids)->not->toContain($custom->id)
        // ...and the rest of the list is untouched: this is a filter on one
        // row, not an empty response.
        ->and($ids)->toContain(seededCustomContractStatuses()->last()->id);
});

it('for-select returns the deactivated custom status with include_inactive=1', function () {
    $actor = contractStatusInactiveReorderUserWith(['viewAny']);
    $custom = seededCustomContractStatuses()->first();
    $custom->update(['is_active' => false]);
    Sanctum::actingAs($actor);

    $ids = collect($this->getJson('/api/contract-statuses/for-select?include_inactive=1')->assertOk()->json('items'))
        ->pluck('id');

    expect($ids)->toContain($custom->id);
});

it('for-select treats include_inactive=0 exactly as its absence', function () {
    $actor = contractStatusInactiveReorderUserWith(['viewAny']);
    $custom = seededCustomContractStatuses()->first();
    $custom->update(['is_active' => false]);
    Sanctum::actingAs($actor);

    $ids = collect($this->getJson('/api/contract-statuses/for-select?include_inactive=0')->assertOk()->json('items'))
        ->pluck('id');

    expect($ids)->not->toContain($custom->id);
});

it('for-select rejects a non-boolean include_inactive', function () {
    $actor = contractStatusInactiveReorderUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/contract-statuses/for-select?include_inactive=maybe')
        ->assertStatus(422)->assertJsonValidationErrors('include_inactive');
});

// ---------------------------------------------------------------------------
// 3 — the end-to-end cycle that was broken
// ---------------------------------------------------------------------------

it('the reorder set taken from the DEFAULT for-select is rejected once a custom status is deactivated', function () {
    // The exact failure the user hit: the sheet read the filtered list and
    // submitted it, so the guard saw an incomplete set on every drag.
    $actor = contractStatusInactiveReorderUserWith(['viewAny', 'update']);
    seededCustomContractStatuses()->first()->update(['is_active' => false]);
    Sanctum::actingAs($actor);

    $customSystemKeys = ContractStatus::query()->whereNull('system_key')->pluck('id')->all();

    $filteredIds = collect($this->getJson('/api/contract-statuses/for-select')->assertOk()->json('items'))
        ->pluck('id')
        ->filter(fn (int $id): bool => in_array($id, $customSystemKeys, true))
        ->values()
        ->all();

    $this->postJson('/api/contract-statuses/reorder', ['ordered_ids' => $filteredIds])
        ->assertStatus(422)
        ->assertJsonPath('message', 'ordered_ids must contain exactly the custom statuses (no system status, none missing).');
});

it('the reorder set taken WITH include_inactive is accepted and the new order persists', function () {
    $actor = contractStatusInactiveReorderUserWith(['viewAny', 'update']);
    $customs = seededCustomContractStatuses();
    $deactivated = $customs->first();
    $deactivated->update(['is_active' => false]);
    Sanctum::actingAs($actor);

    // Taken the way the sheet takes it now, then reversed and submitted.
    $fullIds = collect($this->getJson('/api/contract-statuses/for-select?include_inactive=1')->assertOk()->json('items'))
        ->pluck('id')
        ->filter(fn (int $id): bool => $customs->contains('id', $id))
        ->values()
        ->all();

    expect($fullIds)->toHaveCount($customs->count())
        ->and($fullIds)->toContain($deactivated->id);

    $reversed = array_reverse($fullIds);

    $this->postJson('/api/contract-statuses/reorder', ['ordered_ids' => $reversed])->assertOk();

    expect(ContractStatus::query()->whereNull('system_key')->orderBy('sort_order')->pluck('id')->all())
        ->toBe($reversed);

    // A deactivated row is still an ORDERED row: it keeps its place in the
    // sequence rather than being pushed out of it.
    expect($deactivated->fresh()->is_active)->toBeFalse();
});

// ---------------------------------------------------------------------------
// 4 — the guard was NOT loosened
// ---------------------------------------------------------------------------

it('omitting the deactivated custom status is STILL a 422, and no order changes', function () {
    $actor = contractStatusInactiveReorderUserWith(['viewAny', 'update']);
    $customs = seededCustomContractStatuses();
    $deactivated = $customs->first();
    $deactivated->update(['is_active' => false]);
    $orderBefore = ContractStatus::query()->orderBy('sort_order')->pluck('id')->all();
    Sanctum::actingAs($actor);

    // The whole point of the fix: the READ widened, the GUARD did not.
    $withoutDeactivated = $customs->skip(1)->pluck('id')->all();

    $this->postJson('/api/contract-statuses/reorder', ['ordered_ids' => $withoutDeactivated])
        ->assertStatus(422)
        ->assertJsonPath('message', 'ordered_ids must contain exactly the custom statuses (no system status, none missing).');

    expect(ContractStatus::query()->orderBy('sort_order')->pluck('id')->all())->toBe($orderBefore);
});

it('a deactivated SYSTEM-adjacent payload is still rejected: system rows stay out of the valid set', function () {
    $actor = contractStatusInactiveReorderUserWith(['viewAny', 'update']);
    $customs = seededCustomContractStatuses();
    $customs->first()->update(['is_active' => false]);
    $systemRow = ContractStatus::query()->whereNotNull('system_key')->firstOrFail();
    $orderBefore = ContractStatus::query()->orderBy('sort_order')->pluck('id')->all();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses/reorder', [
        'ordered_ids' => [...$customs->pluck('id')->all(), $systemRow->id],
    ])->assertStatus(422);

    expect(ContractStatus::query()->orderBy('sort_order')->pluck('id')->all())->toBe($orderBefore);
});

it('deactivating a custom status does not make it reorderable without the update permission', function () {
    $actor = contractStatusInactiveReorderUserWith(['viewAny']);
    $customs = seededCustomContractStatuses();
    $customs->first()->update(['is_active' => false]);
    $orderBefore = ContractStatus::query()->orderBy('sort_order')->pluck('id')->all();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses/reorder', [
        'ordered_ids' => array_reverse($customs->pluck('id')->all()),
    ])->assertForbidden();

    expect(ContractStatus::query()->orderBy('sort_order')->pluck('id')->all())->toBe($orderBefore);
});
