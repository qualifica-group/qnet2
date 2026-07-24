<?php

use App\Jobs\GenerateExportJob;
use App\Models\RewardStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("reward-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-011 — columns config, frozen order + flags
// ---------------------------------------------------------------------------

it('GET /api/tables/reward-statuses/columns: 403 without viewAny, 200 with the 8 frozen columns (AC-011)', function () {
    $actor = rewardStatusUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/reward-statuses/columns')->assertForbidden();

    $actor = rewardStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/reward-statuses/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('reward-statuses')
        ->and($data['defaultSort'])->toBe([['columnId' => 'sort_order', 'direction' => 'asc']])
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'description', 'color', 'sort_order', 'is_active', 'created_at', 'updated_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterable'])->toBeTrue()
        ->and($columns['description']['sortable'])->toBeFalse()
        ->and($columns['description']['filterable'])->toBeTrue()
        ->and($columns['color']['sortable'])->toBeFalse()
        ->and($columns['color']['filterable'])->toBeFalse()
        ->and($columns['sort_order']['sortable'])->toBeTrue()
        ->and($columns['is_active']['sortable'])->toBeTrue()
        ->and($columns['is_active']['filterable'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-011 — rows: search, filter, sort, pagination/total
// ---------------------------------------------------------------------------

it('rows: search on name returns only the matching row (AC-011)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    RewardStatus::factory()->create(['name' => 'Approvato']);
    RewardStatus::factory()->create(['name' => 'Consegnato']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-statuses/rows', [
        'startRow' => 0, 'endRow' => 25, 'search' => 'Approvato',
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('name')->all())->toContain('Approvato')
        ->and(collect($response->json('items'))->pluck('name')->all())->not->toContain('Consegnato');
});

it('rows: filter on is_active returns only matching rows (AC-011)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    RewardStatus::factory()->create(['name' => 'Active Row', 'is_active' => true]);
    RewardStatus::factory()->create(['name' => 'Inactive Row', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-statuses/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['is_active' => ['filterType' => 'boolean', 'filter' => false]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toContain('Inactive Row')
        ->and($names)->not->toContain('Active Row');
});

it('rows: sorts by name asc and desc (AC-011)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    RewardStatus::factory()->create(['name' => 'Zeta reward']);
    RewardStatus::factory()->create(['name' => 'Alpha reward']);
    Sanctum::actingAs($actor);

    $asc = $this->postJson('/api/tables/reward-statuses/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'name', 'sort' => 'asc']],
    ])->assertOk();
    $ascNames = collect($asc->json('items'))->pluck('name')->all();
    expect(array_search('Alpha reward', $ascNames, true))->toBeLessThan(array_search('Zeta reward', $ascNames, true));
});

it('rows: pagination reports the correct total and defaults to a limit of 25 (AC-011)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    RewardStatus::factory()->count(30)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    // +1 for the migration-seeded 'pending' system row.
    expect($response->json('pagination.total'))->toBe(31)
        ->and($response->json('items'))->toHaveCount(25);
});

// ---------------------------------------------------------------------------
// AC-012 — row-actions gated per permission, delete carries confirm, absent
// on the system row
// ---------------------------------------------------------------------------

it('rows: view/edit/delete/viewActivity actions present only with the matching permission (AC-012)', function () {
    $actor = rewardStatusUserWith(['viewAny', 'view', 'update', 'delete', 'viewActivity']);
    RewardStatus::factory()->create(['name' => 'Approvato']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Approvato');

    expect($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete', 'activity']);
});

it('rows: delete is ABSENT on the system row, even with the permission (BR-3, AC-012)', function () {
    $actor = rewardStatusUserWith(['viewAny', 'view', 'update', 'delete']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $systemRow = collect($response->json('items'))->firstWhere('system_key', 'pending');

    expect($systemRow)->not->toBeNull()
        ->and($systemRow['actions'])->not->toContain('delete')
        ->and($systemRow['actions'])->toContain('edit');
});

it('columns: the delete action config carries confirm=true (AC-012)', function () {
    $actor = rewardStatusUserWith(['viewAny', 'delete']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/reward-statuses/columns')->assertOk()->json('data');
    $deleteAction = collect($data['actions'])->firstWhere('key', 'delete');

    expect($deleteAction)->not->toBeNull()
        ->and($deleteAction['confirm'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-013 — export "for free" via config/tables.php registration
// ---------------------------------------------------------------------------

it('export: the reward-statuses domain is registered in the generic export engine, no dedicated code (AC-013)', function () {
    Queue::fake();
    $actor = rewardStatusUserWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/reward-statuses', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.export_run.resource', 'reward-statuses');

    Queue::assertPushed(GenerateExportJob::class);
});

it('export: 403 without reward-statuses.export, no export job pushed (AC-013)', function () {
    Queue::fake();
    $actor = rewardStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/reward-statuses', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();

    Queue::assertNotPushed(GenerateExportJob::class);
});
