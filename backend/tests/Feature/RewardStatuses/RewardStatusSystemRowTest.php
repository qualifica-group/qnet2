<?php

use App\Enums\RewardStatusGroup;
use App\Models\RewardStatus;
use App\Models\User;
use Database\Seeders\DemoRewardStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| System-status rules for `reward-statuses` (spec 0060, D-2)
|--------------------------------------------------------------------------
|
| The ONE mandatory row ("In attesa"/`pending`) is seeded unconditionally by
| the create-table migration, so every test here reads it back rather than
| creating it (system_key is UNIQUE — a second 'pending' row would violate
| it).
*/

if (! function_exists('rewardStatusSystemUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardStatusSystemUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
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
// AC-017 — clean seed has ONLY the "pending" system row
// ---------------------------------------------------------------------------

it('migrate:fresh creates the FOUR system rows in their pinned order, no other status (spec 0073, AC-001)', function () {
    expect(RewardStatus::count())->toBe(4);

    $rows = RewardStatus::query()->orderBy('sort_order')->get();

    expect($rows->pluck('name')->all())->toBe(['Aperto', 'In attesa', 'Chiuso positivo', 'Chiuso negativo'])
        ->and($rows->pluck('system_key')->all())->toBe(['new', 'pending', 'won', 'lost'])
        ->and($rows->pluck('group')->map->value->all())->toBe(['open', 'pending', 'closed_won', 'closed_lost'])
        ->and($rows->pluck('sort_order')->all())->toBe([0, 10, 20, 30])
        ->and($rows->every(fn (RewardStatus $row): bool => $row->is_active))->toBeTrue();

    $pending = $rows->firstWhere('system_key', 'pending');
    expect($pending->color)->toBe('amber');
});

it('running DemoRewardStatusSeeder twice does not duplicate rows (AC-017)', function () {
    $this->seed(DemoRewardStatusSeeder::class);
    $countAfterFirstRun = RewardStatus::count();

    $this->seed(DemoRewardStatusSeeder::class);

    expect(RewardStatus::count())->toBe($countAfterFirstRun);
});

// ---------------------------------------------------------------------------
// BR-3 — delete guard on the system row (AC-005)
// ---------------------------------------------------------------------------

it('delete: 422 on the system "pending" row, message names it, row persists (BR-3, AC-005)', function () {
    $actor = rewardStatusSystemUserWith(['delete']);
    $pending = RewardStatus::where('system_key', 'pending')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-statuses/{$pending->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', "The '{$pending->name}' status is a system status and cannot be deleted.");

    $this->assertDatabaseHas('reward_statuses', ['id' => $pending->id]);
});

it('delete: a custom, unreferenced row still returns 204 (invariant)', function () {
    $actor = rewardStatusSystemUserWith(['delete']);
    $custom = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-statuses/{$custom->id}")->assertNoContent();
});

// ---------------------------------------------------------------------------
// BR-4 — delete guard via the generic bulk-delete endpoint (table framework)
// ---------------------------------------------------------------------------

it('bulk-delete: the system row is rejected, a custom unreferenced row succeeds (BR-3)', function () {
    $actor = rewardStatusSystemUserWith(['delete', 'viewAny']);
    $pending = RewardStatus::where('system_key', 'pending')->firstOrFail();
    $custom = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-statuses/bulk-delete', ['ids' => [$pending->id, $custom->id]])
        ->assertOk();

    expect($response->json('data.deleted'))->toBe(1)
        ->and(collect($response->json('data.failed'))->pluck('id'))->toContain($pending->id)
        ->and(collect($response->json('data.failed'))->firstWhere('id', $pending->id)['reason'])->toBe('guarded');

    $this->assertDatabaseHas('reward_statuses', ['id' => $pending->id]);
    $this->assertDatabaseMissing('reward_statuses', ['id' => $custom->id]);
});

// ---------------------------------------------------------------------------
// BR-3 — update guard: name/color allowed, description/is_active/sort_order
// rejected
// ---------------------------------------------------------------------------

it('update: 200 when the system row changes ONLY name/color (BR-3, AC-005)', function () {
    $actor = rewardStatusSystemUserWith(['update']);
    $pending = RewardStatus::where('system_key', 'pending')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$pending->id}", ['name' => 'In attesa (revisionata)', 'color' => 'teal'])
        ->assertOk()
        ->assertJsonPath('data.name', 'In attesa (revisionata)')
        ->assertJsonPath('data.color', 'teal')
        ->assertJsonPath('data.system_key', 'pending');

    $this->assertDatabaseHas('reward_statuses', ['id' => $pending->id, 'name' => 'In attesa (revisionata)', 'color' => 'teal']);
});

it('update: 422 when the system row payload includes group, the phase never moves (spec 0073, AC-004)', function () {
    $actor = rewardStatusSystemUserWith(['update']);
    $pending = RewardStatus::where('system_key', 'pending')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$pending->id}", ['group' => 'closed_won'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    expect($pending->fresh()->group)->toBe(RewardStatusGroup::Pending);
});

it('a row inserted without an explicit group falls back to open (spec 0073, AC-002)', function () {
    $id = DB::table('reward_statuses')->insertGetId([
        'name' => 'Riga preesistente',
        'color' => 'slate',
        'sort_order' => 50,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(RewardStatus::findOrFail($id)->group)->toBe(RewardStatusGroup::Open);
});

it('update: 422 when the system row payload includes description, nothing persists (BR-3, AC-005)', function () {
    $actor = rewardStatusSystemUserWith(['update']);
    $pending = RewardStatus::where('system_key', 'pending')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$pending->id}", ['description' => 'Nuova descrizione'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    $this->assertDatabaseHas('reward_statuses', ['id' => $pending->id, 'description' => null]);
});

it('update: 422 when the system row payload includes is_active, is_active stays true (BR-3, AC-005)', function () {
    $actor = rewardStatusSystemUserWith(['update']);
    $pending = RewardStatus::where('system_key', 'pending')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$pending->id}", ['is_active' => false])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    $this->assertDatabaseHas('reward_statuses', ['id' => $pending->id, 'is_active' => true]);
});

it('update: a custom row freely accepts description/is_active', function () {
    $actor = rewardStatusSystemUserWith(['update']);
    $custom = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$custom->id}", ['description' => 'Una nuova descrizione', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.description', 'Una nuova descrizione')
        ->assertJsonPath('data.is_active', false);
});

// ---------------------------------------------------------------------------
// mapRow exposure of system_key
// ---------------------------------------------------------------------------

it('table rows: mapRow exposes system_key for the pending row and null for custom rows', function () {
    $actor = rewardStatusSystemUserWith(['viewAny']);
    RewardStatus::factory()->create(['name' => 'Custom Row']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/reward-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $pendingRow = collect($response->json('items'))->firstWhere('system_key', 'pending');
    expect($pendingRow)->not->toBeNull()
        ->and($pendingRow['actions'] ?? [])->not->toContain('delete');

    $customRow = collect($response->json('items'))->firstWhere('name', 'Custom Row');
    expect($customRow['system_key'])->toBeNull();
});
