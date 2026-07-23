<?php

use App\Jobs\GenerateExportJob;
use App\Models\RewardType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardTypeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardTypeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("reward-types.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-types.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-010 — columns config, frozen order + flags
// ---------------------------------------------------------------------------

it('GET /api/tables/reward-types/columns: 403 without viewAny, 200 with the 5 frozen columns (AC-010)', function () {
    $actor = rewardTypeUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/reward-types/columns')->assertForbidden();

    $actor = rewardTypeUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/reward-types/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('reward-types')
        ->and($data['defaultSort'])->toBe([['columnId' => 'name', 'direction' => 'asc']])
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'color', 'created_at', 'updated_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterable'])->toBeTrue()
        ->and($columns['color']['sortable'])->toBeFalse()
        ->and($columns['color']['filterable'])->toBeFalse()
        ->and($columns['created_at']['sortable'])->toBeTrue()
        ->and($columns['created_at']['filterable'])->toBeTrue()
        ->and($columns['updated_at']['sortable'])->toBeTrue()
        ->and($columns['updated_at']['filterable'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-010 — rows: search, filter, sort asc/desc, pagination/total
// ---------------------------------------------------------------------------

it('rows: search on name returns only the matching row (AC-010)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    RewardType::factory()->create(['name' => 'Buono Amazon']);
    RewardType::factory()->create(['name' => 'Buono carburante']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-types/rows', [
        'startRow' => 0, 'endRow' => 25, 'search' => 'Amazon',
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and(collect($response->json('items'))->pluck('name')->all())->toBe(['Buono Amazon']);
});

it('rows: filter on name returns only the matching row (AC-010)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    RewardType::factory()->create(['name' => 'Buono Amazon']);
    RewardType::factory()->create(['name' => 'Buono carburante']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-types/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Amazon']],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('name')->all())->toBe(['Buono Amazon']);
});

it('rows: sorts by name asc and desc (AC-010)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    RewardType::factory()->create(['name' => 'Zeta reward']);
    RewardType::factory()->create(['name' => 'Alpha reward']);
    Sanctum::actingAs($actor);

    $asc = $this->postJson('/api/tables/reward-types/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'name', 'sort' => 'asc']],
    ])->assertOk();
    expect(collect($asc->json('items'))->pluck('name')->all())->toBe(['Alpha reward', 'Zeta reward']);

    $desc = $this->postJson('/api/tables/reward-types/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'name', 'sort' => 'desc']],
    ])->assertOk();
    expect(collect($desc->json('items'))->pluck('name')->all())->toBe(['Zeta reward', 'Alpha reward']);
});

it('rows: sorts by created_at asc and desc (AC-010)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    $older = RewardType::factory()->create(['name' => 'Older', 'created_at' => now()->subDays(2)]);
    $newer = RewardType::factory()->create(['name' => 'Newer', 'created_at' => now()]);
    Sanctum::actingAs($actor);

    $asc = $this->postJson('/api/tables/reward-types/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'created_at', 'sort' => 'asc']],
    ])->assertOk();
    expect(collect($asc->json('items'))->pluck('id')->all())->toBe([$older->id, $newer->id]);

    $desc = $this->postJson('/api/tables/reward-types/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'created_at', 'sort' => 'desc']],
    ])->assertOk();
    expect(collect($desc->json('items'))->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

it('rows: pagination reports the correct total and defaults to a limit of 25 (AC-010)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    RewardType::factory()->count(30)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-types/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    expect($response->json('pagination.total'))->toBe(30)
        ->and($response->json('items'))->toHaveCount(25);
});

// ---------------------------------------------------------------------------
// AC-011 — row-actions gated per permission, delete carries confirm
// ---------------------------------------------------------------------------

it('rows: view/edit/delete/viewActivity actions present only with the matching permission (AC-011)', function () {
    $actor = rewardTypeUserWith(['viewAny', 'view', 'update', 'delete', 'viewActivity']);
    RewardType::factory()->create(['name' => 'Buono Amazon']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-types/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Buono Amazon');

    expect($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete', 'activity']);
});

it('rows: no action present when the actor holds no permission (AC-011)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    RewardType::factory()->create(['name' => 'Buono Amazon']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-types/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Buono Amazon');

    expect($row['actions'])->toBe([]);
});

it('columns: the delete action config carries confirm=true (AC-011)', function () {
    $actor = rewardTypeUserWith(['viewAny', 'delete']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/reward-types/columns')->assertOk()->json('data');
    $deleteAction = collect($data['actions'])->firstWhere('key', 'delete');

    expect($deleteAction)->not->toBeNull()
        ->and($deleteAction['confirm'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-012 — export "for free" via config/tables.php registration
// ---------------------------------------------------------------------------

it('export: the reward-types domain is registered in the generic export engine, no dedicated code (AC-012)', function () {
    Queue::fake();
    $actor = rewardTypeUserWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/reward-types', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.export_run.resource', 'reward-types');

    Queue::assertPushed(GenerateExportJob::class);
});

it('export: 403 without reward-types.export, no export job pushed (AC-012)', function () {
    Queue::fake();
    $actor = rewardTypeUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/reward-types', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();

    Queue::assertNotPushed(GenerateExportJob::class);
});
