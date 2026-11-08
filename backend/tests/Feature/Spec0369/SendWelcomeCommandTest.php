<?php

use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-001: emails every active non-service user a new working password, flags them and revokes their sessions', function () {
    Notification::fake();
    $users = User::factory()->count(3)->create();
    $users->each(fn (User $user) => $user->createToken('api'));

    $this->artisan('users:send-welcome', ['--force' => true])
        ->expectsOutput('Email di benvenuto inviata a 3 utenti.')
        ->assertSuccessful();

    foreach ($users as $user) {
        $user->refresh();
        $password = firstAccessSentWelcome($user)->toMail($user)->viewData['password'];

        expect($user->must_set_password)->toBeTrue()
            ->and($user->welcome_sent_at)->not->toBeNull()
            ->and($user->tokens()->count())->toBe(0)
            ->and(Hash::check($password, $user->password))->toBeTrue();
    }
});

it('AC-002: leaves inactive users, service accounts and already welcomed users untouched', function () {
    Notification::fake();
    $inactive = User::factory()->create(['is_active' => false]);
    $service = User::factory()->create(['is_service_account' => true]);
    $welcomed = User::factory()->create();
    $welcomed->forceFill(['welcome_sent_at' => now()->subDay()])->save();

    $skipped = [$inactive, $service, $welcomed];
    $before = [];
    foreach ($skipped as $user) {
        $user->createToken('api');
        $before[$user->id] = $user->fresh()->password;
    }

    $this->artisan('users:send-welcome', ['--force' => true])
        ->expectsOutput('Email di benvenuto inviata a 0 utenti.')
        ->assertSuccessful();

    foreach ($skipped as $user) {
        $fresh = $user->fresh();
        expect($fresh->password)->toBe($before[$user->id])
            ->and($fresh->must_set_password)->toBeFalse()
            ->and($fresh->tokens()->count())->toBe(1);
    }
    Notification::assertNothingSent();
});

it('AC-003: a second run sends nothing', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->artisan('users:send-welcome', ['--force' => true])->assertSuccessful();
    $password = $user->fresh()->password;

    $this->artisan('users:send-welcome', ['--force' => true])
        ->expectsOutput('Email di benvenuto inviata a 0 utenti.')
        ->assertSuccessful();

    expect($user->fresh()->password)->toBe($password);
    Notification::assertSentToTimes($user, WelcomeUserNotification::class, 1);
});

it('AC-004: --dry-run and a declined confirmation write and send nothing', function () {
    Notification::fake();
    $user = User::factory()->create();
    $password = $user->fresh()->password;

    $this->artisan('users:send-welcome', ['--dry-run' => true])
        ->expectsOutput("1 utenti riceveranno l'email di benvenuto.")
        ->assertSuccessful();

    $this->artisan('users:send-welcome')
        ->expectsConfirmation('1 utenti riceveranno una nuova password via email e verranno disconnessi. Procedere?', 'no')
        ->expectsOutput('Operazione annullata: nessun utente modificato.')
        ->assertSuccessful();

    $fresh = $user->fresh();
    expect($fresh->password)->toBe($password)
        ->and($fresh->welcome_sent_at)->toBeNull()
        ->and($fresh->must_set_password)->toBeFalse();
    Notification::assertNothingSent();
});

it('AC-005: the resend from the user sheet and the user creation set welcome_sent_at', function () {
    Notification::fake();
    $target = User::factory()->create();

    Sanctum::actingAs(userWithUserAbilities(['update', 'create']));
    $this->postJson("/api/users/{$target->id}/resend-welcome")->assertOk();
    $this->postJson('/api/users', firstAccessCreatePayload())->assertCreated();

    expect($target->fresh()->welcome_sent_at)->not->toBeNull()
        ->and(User::where('email', 'new.person@example.com')->firstOrFail()->welcome_sent_at)->not->toBeNull();
});

it('AC-006: the migration is reversible', function () {
    $migration = require database_path('migrations/2026_11_28_100000_add_welcome_sent_at_to_users_table.php');

    $migration->down();
    expect(Schema::hasColumn('users', 'welcome_sent_at'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('users', 'welcome_sent_at'))->toBeTrue();
});
