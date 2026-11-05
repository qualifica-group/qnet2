<?php

use App\Models\User;
use Database\Seeders\QualificaPasswordChangeSeeder;
use Database\Seeders\QualificaProductionDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** Evaluates config/seeding.php with the given APP_ENV and no override. */
function requirePasswordChangeDefaultFor(string $environment): bool
{
    $saved = [$_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null, getenv('APP_ENV')];

    try {
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $environment;
        putenv("APP_ENV={$environment}");
        unset($_SERVER['SEED_REQUIRE_PASSWORD_CHANGE'], $_ENV['SEED_REQUIRE_PASSWORD_CHANGE']);
        putenv('SEED_REQUIRE_PASSWORD_CHANGE');

        return (require config_path('seeding.php'))['require_password_change'];
    } finally {
        [$_SERVER['APP_ENV'], $_ENV['APP_ENV']] = [$saved[0], $saved[1]];
        putenv($saved[2] === false ? 'APP_ENV' : "APP_ENV={$saved[2]}");
    }
}

it('AC-001: flags only active, non-service users who never chose a password, without emails or revoked tokens', function () {
    Notification::fake();
    config(['seeding.require_password_change' => true]);
    $never = User::factory()->create();
    $token = $never->createToken('web');
    $inactive = User::factory()->create(['is_active' => false]);
    $service = User::factory()->create(['is_service_account' => true]);
    $changed = User::factory()->create();
    $changed->forceFill(['password_changed_at' => now()->subDay()])->save();

    $this->seed(QualificaPasswordChangeSeeder::class);

    expect($never->fresh()->must_set_password)->toBeTrue()
        ->and($inactive->fresh()->must_set_password)->toBeFalse()
        ->and($service->fresh()->must_set_password)->toBeFalse()
        ->and($changed->fresh()->must_set_password)->toBeFalse()
        ->and($never->tokens()->whereKey($token->accessToken->id)->exists())->toBeTrue();
    Notification::assertNothingSent();
});

it('AC-002: a re-run does not flag again a user who changed the password in between', function () {
    config(['seeding.require_password_change' => true]);
    $user = User::factory()->create();

    $this->seed(QualificaPasswordChangeSeeder::class);
    // A real token: the first-password request rejects Sanctum::actingAs's mock token.
    $this->withToken($user->createToken('web')->plainTextToken)->putJson('/api/auth/me/first-password', [
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'Brand-New-Pass1!',
    ])->assertOk();
    expect($user->fresh()->must_set_password)->toBeFalse();

    $this->seed(QualificaPasswordChangeSeeder::class);

    expect($user->fresh()->must_set_password)->toBeFalse();
});

it('AC-003: with the switch off nobody is modified', function () {
    config(['seeding.require_password_change' => false]);
    $user = User::factory()->create();

    $this->seed(QualificaPasswordChangeSeeder::class);

    expect($user->fresh()->must_set_password)->toBeFalse();
});

it('AC-004: the default is on only in production', function (string $environment, bool $expected) {
    expect(requirePasswordChangeDefaultFor($environment))->toBe($expected);
})->with([
    'production' => ['production', true],
    'local' => ['local', false],
    'staging' => ['staging', false],
    'testing' => ['testing', false],
]);

it('AC-005: only the user choosing a password stamps password_changed_at', function () {
    Notification::fake();
    // Created first: the permission registrar caches its catalogue on the first request.
    $admin = userWithUserAbilities(['create', 'update']);

    $self = User::factory()->create();
    Sanctum::actingAs($self);
    $this->putJson('/api/auth/me/password', [
        'current_password' => 'password',
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'Brand-New-Pass1!',
    ])->assertOk();
    expect($self->fresh()->password_changed_at)->not->toBeNull();

    $pending = firstAccessPendingUser();
    Auth::forgetGuards();
    $this->withToken($pending->createToken('web')->plainTextToken)->putJson('/api/auth/me/first-password', [
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'Brand-New-Pass1!',
    ])->assertOk();
    expect($pending->fresh()->password_changed_at)->not->toBeNull();

    $forgot = User::factory()->create();
    Auth::forgetGuards();
    $this->withoutToken()->postJson('/api/auth/reset-password', [
        'token' => app('auth.password.broker')->createToken($forgot),
        'email' => $forgot->email,
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'Brand-New-Pass1!',
    ])->assertOk();
    expect($forgot->fresh()->password_changed_at)->not->toBeNull();

    Sanctum::actingAs($admin);
    $created = $this->postJson('/api/users', firstAccessCreatePayload())->assertCreated()->json('data.id');
    $resent = User::factory()->create();
    $this->postJson("/api/users/{$resent->id}/resend-welcome")->assertOk();
    $edited = User::factory()->create();
    $this->patchJson("/api/users/{$edited->id}", ['password' => 'Str0ng-P4ssw0rd!', 'password_confirmation' => 'Str0ng-P4ssw0rd!'])->assertOk();

    expect(User::find($created)->password_changed_at)->toBeNull()
        ->and($resent->fresh()->password_changed_at)->toBeNull()
        ->and($edited->fresh()->password_changed_at)->toBeNull();
});

it('AC-006: the production seed runs it as its last step', function () {
    $recorder = new class extends QualificaProductionDataSeeder
    {
        /** @var array<int, string> */
        public array $recorded = [];

        public function call($class, $silent = false, array $parameters = [])
        {
            $this->recorded[] = $class;

            return $this;
        }

        public function callWith($class, array $parameters = [])
        {
            return $this->call($class);
        }
    };

    $recorder->run();

    expect(end($recorder->recorded))->toBe(QualificaPasswordChangeSeeder::class);
});

it('AC-007: the legacy command is gone and the migration is reversible', function () {
    $this->artisan('list')->doesntExpectOutputToContain('users:require-legacy-password-change');

    $migration = require database_path('migrations/2026_11_19_100300_add_password_changed_at_to_users_table.php');
    $migration->down();
    expect(Schema::hasColumn('users', 'password_changed_at'))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumn('users', 'password_changed_at'))->toBeTrue();
});
