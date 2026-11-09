<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

function enableMaintenance(array $excluded_user_ids = []): void
{
    config([
        'app.maintenance.enabled' => true,
        'app.maintenance.excluded_user_ids' => $excluded_user_ids,
    ]);
}

function assertMaintenanceResponse($response): void
{
    $response->assertStatus(503)->assertExactJson([
        'success' => false,
        'message' => 'The site is under maintenance. Please try again later.',
        'maintenance' => true,
    ]);
}

function attemptLogin($test, User $user, string $password = 'password')
{
    return $test->postJson('/api/auth/login', ['email' => $user->email, 'password' => $password]);
}

it('answers 200 on availability when maintenance is off', function () {
    $this->getJson('/api/availability')->assertOk()->assertJsonPath('success', true);
});

it('answers 503 with the marker to a guest', function () {
    enableMaintenance();

    assertMaintenanceResponse($this->getJson('/api/availability'));
});

it('answers 503 instead of 401 on a protected route', function () {
    enableMaintenance();

    assertMaintenanceResponse($this->getJson('/api/auth/me'));
});

it('translates the message', function () {
    enableMaintenance();

    $this->getJson('/api/availability', ['Accept-Language' => 'it'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Il sito è in manutenzione. Riprova più tardi.');
});

it('keeps the public config reachable', function () {
    enableMaintenance();

    $this->getJson('/api/config')->assertOk();
});

it('lets the token of an excluded user through', function () {
    $user = User::factory()->create();
    enableMaintenance([$user->id]);

    $this->withToken($user->createToken('test')->plainTextToken)
        ->getJson('/api/auth/me')
        ->assertOk();
});

it('blocks the token of a user who is not excluded', function () {
    $excluded = User::factory()->create();
    $other = User::factory()->create();
    enableMaintenance([$excluded->id]);

    assertMaintenanceResponse(
        $this->withToken($other->createToken('test')->plainTextToken)->getJson('/api/auth/me'),
    );
});

it('lets an impersonation token through when the impersonator is excluded', function () {
    $impersonator = User::factory()->create();
    $target = User::factory()->create();
    enableMaintenance([$impersonator->id]);

    $issued = $target->createToken('impersonation');
    $issued->accessToken->forceFill(['impersonated_by' => $impersonator->id])->save();

    $this->withToken($issued->plainTextToken)->getJson('/api/auth/me')->assertOk();
});

it('signs in an excluded user', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    enableMaintenance([$user->id]);

    attemptLogin($this, $user)->assertOk()->assertJsonStructure(['data' => ['token']]);
});

it('refuses a user who is not excluded after the credentials check', function () {
    $user = User::factory()->create(['password' => bcrypt('password'), 'last_login_at' => null]);
    enableMaintenance();

    assertMaintenanceResponse(attemptLogin($this, $user));

    expect(PersonalAccessToken::query()->count())->toBe(0)
        ->and($user->fresh()->last_login_at)->toBeNull();
});

it('answers a wrong password as usual', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    enableMaintenance();

    attemptLogin($this, $user, 'wrong-password')->assertUnprocessable();
});

it('does not read the database when nobody is excluded', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    enableMaintenance();

    DB::enableQueryLog();
    $this->withToken($token)->getJson('/api/auth/me')->assertStatus(503);

    expect(DB::getQueryLog())->toBe([]);
});
