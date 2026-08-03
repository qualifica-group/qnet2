<?php

use App\Models\Reward;
use App\Models\RewardStatus;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * DatabaseMigrations, NOT RefreshDatabase: replaying up() alters the `group`
 * column, which on SQLite means rebuilding the table, which Laravel guards
 * with `PRAGMA foreign_keys = 0` — a no-op inside a transaction, and
 * RefreshDatabase wraps every test in one. A real `php artisan migrate` never
 * hits that (the SQLite grammar does not support schema transactions), so the
 * constraint failure would be an artefact of the harness, not of the
 * migration.
 */
uses(DatabaseMigrations::class);

/*
|--------------------------------------------------------------------------
| Data paths of 2026_08_03_150100_reshape_reward_status_system_rows
|--------------------------------------------------------------------------
|
| migrate:fresh already exercises the rename and the "Aperto" removal on an
| empty table (RewardStatusSystemRowTest asserts the resulting three rows).
| What it can NOT exercise is the state an ALREADY SEEDED database is in:
| rewards parked on "Aperto", and a CUSTOM row holding a name the migration
| is about to give a system row — `name` is UNIQUE, so getting that branch
| wrong turns the deploy into an integrity-constraint failure. Both tests
| rebuild that pre-state and replay up().
*/

if (! function_exists('replayRewardStatusReshape')) {
    function replayRewardStatusReshape(): void
    {
        (require database_path('migrations/2026_08_03_150100_reshape_reward_status_system_rows.php'))->up();
    }
}

it('merges a custom row holding a system name instead of colliding on the unique index', function () {
    $won = RewardStatus::query()->where('system_key', 'won')->firstOrFail();
    $won->forceFill(['name' => 'Chiuso positivo'])->save();

    $namesake = RewardStatus::factory()->create(['name' => 'Approvato']);
    $reward = Reward::factory()->create(['reward_status_id' => $namesake->id]);

    replayRewardStatusReshape();

    expect(RewardStatus::query()->where('name', 'Approvato')->pluck('id')->all())->toBe([$won->id])
        ->and($reward->fresh()->reward_status_id)->toBe($won->id)
        ->and(RewardStatus::find($namesake->id))->toBeNull();

    // The trait rolls the migrations back, and the earlier one deletes the
    // `won` row this reward now points at: restrictOnDelete would abort the
    // rollback on a reference this test itself created.
    $reward->delete();
});

it('rehomes the rewards parked on "Aperto" onto "In attesa" before dropping it', function () {
    $pending = RewardStatus::query()->where('system_key', 'pending')->firstOrFail();

    $openId = DB::table('reward_statuses')->insertGetId([
        'name' => 'Aperto', 'color' => 'blue', 'group' => 'pending',
        'sort_order' => 0, 'is_active' => true, 'system_key' => 'new',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $reward = Reward::factory()->create(['reward_status_id' => $openId]);

    replayRewardStatusReshape();

    expect($reward->fresh()->reward_status_id)->toBe($pending->id)
        ->and(RewardStatus::find($openId))->toBeNull();
});
