<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Per-user date/time display preferences. Persisted as two plain string columns
 * on `users`, exposed and saved through the existing GET/PATCH /api/auth/me —
 * no new endpoint, same shape as `locale`/`ui_scale`. Defaults when unset are
 * the Italian day-first pattern on a 24-hour clock.
 */
it('GET /auth/me defaults date_format and time_format when unset', function () {
    $user = User::factory()->create(['date_format' => null, 'time_format' => null]);
    Sanctum::actingAs($user);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.date_format', 'dmy')
        ->assertJsonPath('data.time_format', '24h');
});

it('PATCH /auth/me persists both preferences and a following GET reflects them', function () {
    $user = User::factory()->create(['date_format' => null, 'time_format' => null]);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['date_format' => 'ymd', 'time_format' => '12h'])
        ->assertOk()
        ->assertJsonPath('data.date_format', 'ymd')
        ->assertJsonPath('data.time_format', '12h');

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.date_format', 'ymd')
        ->assertJsonPath('data.time_format', '12h');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'date_format' => 'ymd',
        'time_format' => '12h',
    ]);
});

it('accepts every declared date format', function (string $format) {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['date_format' => $format])
        ->assertOk()
        ->assertJsonPath('data.date_format', $format);
})->with(['dmy', 'mdy', 'ymd']);

it('rejects a date format outside the enum', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['date_format' => 'dd.mm.yyyy'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_format');
});

it('rejects a time format outside the enum', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['time_format' => '36h'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('time_format');
});

it('rejects an empty date format instead of storing a blank', function () {
    $user = User::factory()->create(['date_format' => 'mdy']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['date_format' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_format');

    expect($user->fresh()->date_format)->toBe('mdy');
});

it('updates one preference without disturbing the other', function () {
    $user = User::factory()->create(['date_format' => 'mdy', 'time_format' => '12h']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['date_format' => 'dmy'])
        ->assertOk()
        ->assertJsonPath('data.date_format', 'dmy')
        ->assertJsonPath('data.time_format', '12h');

    expect($user->fresh()->time_format)->toBe('12h');
});

it('omitting both keys leaves the stored values untouched', function () {
    $user = User::factory()->create(['date_format' => 'ymd', 'time_format' => '12h']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['locale' => 'it'])
        ->assertOk()
        ->assertJsonPath('data.date_format', 'ymd')
        ->assertJsonPath('data.time_format', '12h');
});

it('only ever touches the authenticated user, never another one', function () {
    $other = User::factory()->create(['date_format' => null]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['date_format' => 'ymd'])->assertOk();

    expect($other->fresh()->date_format)->toBeNull();
});
