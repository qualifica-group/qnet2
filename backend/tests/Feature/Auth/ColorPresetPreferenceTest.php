<?php

use App\Enums\ColorPresetEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Per-user accent palette. Persisted as a plain string column on `users`,
 * exposed and saved through the existing GET/PATCH /api/auth/me — no new
 * endpoint, same shape as `ui_scale`/`date_format`. Default when unset: 'default'.
 */
it('GET /auth/me defaults color_preset when unset', function () {
    Sanctum::actingAs(User::factory()->create(['color_preset' => null]));

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.color_preset', 'default');
});

it('PATCH /auth/me persists the preset and a following GET reflects it', function () {
    $user = User::factory()->create(['color_preset' => null]);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['color_preset' => 'forest'])
        ->assertOk()
        ->assertJsonPath('data.color_preset', 'forest');

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.color_preset', 'forest');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'color_preset' => 'forest']);
});

it('accepts every declared preset', function (string $preset) {
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/auth/me', ['color_preset' => $preset])
        ->assertOk()
        ->assertJsonPath('data.color_preset', $preset);
})->with(ColorPresetEnum::values());

it('rejects a preset outside the enum', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/auth/me', ['color_preset' => 'neon'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('color_preset');
});

it('rejects an empty preset instead of storing a blank', function () {
    $user = User::factory()->create(['color_preset' => 'plum']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['color_preset' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('color_preset');

    expect($user->fresh()->color_preset)->toBe('plum');
});

it('omitting the key leaves the stored preset untouched', function () {
    Sanctum::actingAs(User::factory()->create(['color_preset' => 'ocean']));

    $this->patchJson('/api/auth/me', ['locale' => 'it'])
        ->assertOk()
        ->assertJsonPath('data.color_preset', 'ocean');
});

it('rejects an unauthenticated request', function () {
    $this->patchJson('/api/auth/me', ['color_preset' => 'forest'])->assertUnauthorized();
});

it('only ever touches the authenticated user, never another one', function () {
    $other = User::factory()->create(['color_preset' => null]);
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/auth/me', ['color_preset' => 'amber'])->assertOk();

    expect($other->fresh()->color_preset)->toBeNull();
});
