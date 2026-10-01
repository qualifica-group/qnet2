<?php

use App\Enums\HealthStatusEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function healthSuperAdmin(): User
{
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    return $user;
}

function healthCheck(array $payload, string $key): array
{
    return collect($payload['data']['checks'])->firstWhere('key', $key);
}

function healthDetail(array $check, string $key): array
{
    return collect($check['details'])->firstWhere('key', $key);
}

// AC-001
it('returns the four checks for a super-admin', function () {
    Sanctum::actingAs(healthSuperAdmin());

    $response = $this->getJson('/api/system-health')->assertOk()->assertJsonPath('success', true);

    expect(array_column($response->json('data.checks'), 'key'))->toBe(['database', 'email', 'queue', 'security'])
        ->and($response->json('data'))->toHaveKeys(['overall', 'checked_at', 'online']);
});

// AC-002
it('rejects non super-admins with 403', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/system-health')->assertForbidden();
});

it('rejects anonymous callers with 401', function () {
    $this->getJson('/api/system-health')->assertUnauthorized();
});

// AC-003
it('exposes the contract shape with valid statuses and a healthy database', function () {
    Sanctum::actingAs(healthSuperAdmin());
    $payload = $this->getJson('/api/system-health')->json();
    $valid = ['ok', 'degraded', 'down'];

    expect($payload['data']['overall'])->toBeIn($valid);
    foreach ($payload['data']['checks'] as $check) {
        expect($check)->toHaveKeys(['key', 'status', 'latency_ms', 'message', 'details'])
            ->and($check['status'])->toBeIn($valid);
        foreach ($check['details'] as $detail) {
            expect($detail)->toHaveKeys(['key', 'status', 'value', 'message'])->and($detail['status'])->toBeIn($valid);
        }
    }

    $database = healthCheck($payload, 'database');
    expect($database['status'])->toBe('ok')->and($database['latency_ms'])->toBeGreaterThanOrEqual(0)
        ->and(array_column($database['details'], 'key'))->toBe(['connection', 'name'])
        ->and(array_column(healthCheck($payload, 'email')['details'], 'key'))->toBe(['mailer', 'transport', 'credentials'])
        ->and(array_column(healthCheck($payload, 'queue')['details'], 'key'))->toBe(['connection', 'pending', 'failed'])
        ->and(array_column(healthCheck($payload, 'security')['details'], 'key'))->toBe([
            'app_debug', 'app_env', 'https', 'sanctum_expiration', 'cors', 'app_key', 'mail_secrets', 'activitylog',
        ]);
});

// AC-004
it('aggregates the worst status', function () {
    expect(HealthStatusEnum::worst(HealthStatusEnum::Ok, HealthStatusEnum::Degraded, HealthStatusEnum::Ok))->toBe(HealthStatusEnum::Degraded)
        ->and(HealthStatusEnum::worst(HealthStatusEnum::Degraded, HealthStatusEnum::Down))->toBe(HealthStatusEnum::Down)
        ->and(HealthStatusEnum::worst(HealthStatusEnum::Ok, HealthStatusEnum::Ok))->toBe(HealthStatusEnum::Ok)
        ->and(HealthStatusEnum::worst())->toBe(HealthStatusEnum::Ok);
});

// AC-005
it('never leaks secrets or internal class names', function () {
    config([
        'app.key' => 'base64:SUPERSECRETAPPKEYVALUE0123456789abcdef=',
        'mail.mailers.microsoft-graph.tenant' => 'tenant-value-xyz',
        'mail.mailers.microsoft-graph.client' => 'client-value-xyz',
        'mail.mailers.microsoft-graph.secret' => 'graph-secret-value-xyz',
    ]);
    Sanctum::actingAs(healthSuperAdmin());

    $body = $this->getJson('/api/system-health')->assertOk()->getContent();

    expect($body)->not->toContain('SUPERSECRETAPPKEYVALUE')
        ->not->toContain('graph-secret-value-xyz')
        ->not->toContain('client-value-xyz')
        ->not->toContain('tenant-value-xyz')
        ->not->toContain('Exception')
        ->not->toContain('App\\\\');

    $security = healthCheck(json_decode($body, true), 'security');
    expect(healthDetail($security, 'mail_secrets')['value'])->toBe('complete')
        ->and(healthDetail($security, 'app_key')['status'])->toBe('ok');
});

// AC-006
it('degrades the queue check on failed jobs and is ok otherwise', function () {
    config(['queue.default' => 'database']);
    Sanctum::actingAs(healthSuperAdmin());

    $queue = healthCheck($this->getJson('/api/system-health')->json(), 'queue');
    expect($queue['status'])->toBe('ok')
        ->and(healthDetail($queue, 'failed')['value'])->toBe('0');

    DB::table('failed_jobs')->insert([
        'uuid' => (string) str()->uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => '{}', 'exception' => 'boom', 'failed_at' => now(),
    ]);

    $queue = healthCheck($this->getJson('/api/system-health')->json(), 'queue');
    expect($queue['status'])->toBe('degraded')
        ->and(healthDetail($queue, 'failed'))->toMatchArray(['status' => 'degraded', 'value' => '1']);
});

it('degrades the queue check on sync connection or pending over threshold', function () {
    Sanctum::actingAs(healthSuperAdmin());

    config(['queue.default' => 'sync']);
    $queue = healthCheck($this->getJson('/api/system-health')->json(), 'queue');
    expect(healthDetail($queue, 'connection')['status'])->toBe('degraded');

    config(['queue.default' => 'database', 'system-health.queue_pending_threshold' => 0]);
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
    $queue = healthCheck($this->getJson('/api/system-health')->json(), 'queue');
    expect(healthDetail($queue, 'pending'))->toMatchArray(['status' => 'degraded', 'value' => '1']);
});

function healthTokenFor(User $user, int $secondsAgo, ?User $impersonatedBy = null): void
{
    $token = $user->createToken($impersonatedBy ? 'impersonation' : 'auth')->accessToken;
    $token->forceFill([
        'last_used_at' => now()->subSeconds($secondsAgo),
        'impersonated_by' => $impersonatedBy?->id,
    ])->save();
}

function healthOnline(): array
{
    return test()->getJson('/api/system-health')->assertOk()->json('data.online');
}

// AC-007
it('counts distinct online people inside the window ordered by last seen', function () {
    $admin = healthSuperAdmin();
    Sanctum::actingAs($admin);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $stale = User::factory()->create();

    healthTokenFor($a, 30);
    healthTokenFor($a, 50);
    healthTokenFor($b, 10);
    healthTokenFor($stale, 600);

    $online = healthOnline();

    expect($online['count'])->toBe(2)
        ->and($online['window_minutes'])->toBe(2)
        ->and(array_column($online['users'], 'id'))->toBe([$b->id, $a->id])
        ->and($online['users'][0])->toHaveKeys(['id', 'name', 'email', 'last_seen_at', 'impersonating'])
        ->and($online['users'][0]['impersonating'])->toBeNull();
});

// AC-008
it('attributes impersonation tokens to the actor', function () {
    Sanctum::actingAs(healthSuperAdmin());
    $actor = User::factory()->create();
    $target = User::factory()->create();

    healthTokenFor($target, 5, $actor);

    $online = healthOnline();

    expect($online['count'])->toBe(1)
        ->and($online['users'][0]['id'])->toBe($actor->id)
        ->and($online['users'][0]['impersonating'])->toBe(['id' => $target->id, 'name' => $target->name]);
});

// AC-009
it('excludes inactive users', function () {
    Sanctum::actingAs(healthSuperAdmin());
    $inactive = User::factory()->create(['is_active' => false]);
    healthTokenFor($inactive, 5);

    expect(healthOnline())->toMatchArray(['count' => 0, 'users' => []]);
});

// AC-010
it('heartbeat returns 204 and refreshes last_used_at', function () {
    $user = User::factory()->create();
    $newToken = $user->createToken('auth');
    $newToken->accessToken->forceFill(['last_used_at' => now()->subHour()])->save();

    $this->withToken($newToken->plainTextToken)->postJson('/api/presence/heartbeat')->assertNoContent();

    expect($newToken->accessToken->fresh()->last_used_at->greaterThan(now()->subMinute()))->toBeTrue();
});

it('heartbeat rejects anonymous callers with 401', function () {
    $this->postJson('/api/presence/heartbeat')->assertUnauthorized();
});

// AC-011
it('shows the system-health navigation item only to super-admins', function () {
    $find = fn () => collect(data_get(
        collect($this->getJson('/api/navigation')->json('data'))->firstWhere('key', 'administration'),
        'children',
        [],
    ))->firstWhere('key', 'system-health');

    Sanctum::actingAs(healthSuperAdmin());
    expect($find()['route'])->toBe('/admin/system-health');

    Sanctum::actingAs(User::factory()->create());
    expect($find())->toBeNull();
});
