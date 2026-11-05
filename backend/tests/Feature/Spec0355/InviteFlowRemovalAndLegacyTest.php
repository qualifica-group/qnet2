<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-008: set-password is gone, the token table is dropped and the migration is reversible', function () {
    $this->postJson('/api/auth/set-password', [
        'token' => 'x', 'email' => 'a@example.com', 'password' => 'Brand-New-Pass1!', 'password_confirmation' => 'Brand-New-Pass1!',
    ])->assertNotFound();

    expect(Schema::hasTable('password_setup_tokens'))->toBeFalse()
        ->and(config('auth.passwords.users_setup'))->toBeNull();

    $migration = require database_path('migrations/2026_11_19_100200_drop_password_setup_tokens_table.php');
    $migration->down();
    expect(Schema::hasTable('password_setup_tokens'))->toBeTrue();
    $migration->up();
    expect(Schema::hasTable('password_setup_tokens'))->toBeFalse();
});

// REQUIREMENT CHANGED (spec 0356): AC-009, the legacy command, is replaced by
// QualificaPasswordChangeSeeder (tests/Feature/Spec0356).

it('AC-010: admin password edit still flags without email and the self change clears the flag', function () {
    Notification::fake();
    $target = User::factory()->create();
    Sanctum::actingAs(userWithUserAbilities(['update']));

    $this->patchJson("/api/users/{$target->id}", ['password' => 'Str0ng-P4ssw0rd!', 'password_confirmation' => 'Str0ng-P4ssw0rd!'])
        ->assertOk()->assertJsonPath('data.must_set_password', true);
    Notification::assertNothingSent();

    $pending = firstAccessPendingUser();
    Sanctum::actingAs($pending);
    $this->putJson('/api/auth/me/password', [
        'current_password' => 'password',
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'Brand-New-Pass1!',
    ])->assertOk();
    expect($pending->fresh()->must_set_password)->toBeFalse();
});
