<?php

use App\Models\User;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

// Since the user directive 2026-09-15 the seeder carries the super-admin alone:
// the tester roster gave way to the real operators (QualificaOperatorSeeder).
uses(RefreshDatabase::class);

const SUPER_ADMIN_EMAIL = 'ciro.cacciapuoti@qualificagroup.com';

it('creates the super-admin account only, standalone on a fresh database', function () {
    $this->seed(TestUsersSeeder::class);

    $user = User::query()->where('email', SUPER_ADMIN_EMAIL)->firstOrFail();

    expect($user->getRoleNames()->all())->toBe(['super-admin'])
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Qualifica2026!', $user->password))->toBeTrue()
        ->and($user->name)->toBe('Ciro Cacciapuoti')
        ->and($user->personalData->first_name)->toBe('Ciro')
        ->and($user->personalData->last_name)->toBe('Cacciapuoti')
        ->and(User::query()->count())->toBe(1);
});

it('is idempotent and restores the shared password on a re-run', function () {
    $this->seed(TestUsersSeeder::class);

    User::query()->where('email', SUPER_ADMIN_EMAIL)->firstOrFail()
        ->forceFill(['password' => Hash::make('changed-by-hand')])->save();

    $this->seed(TestUsersSeeder::class);

    $user = User::query()->where('email', SUPER_ADMIN_EMAIL)->sole();

    expect(Hash::check('Qualifica2026!', $user->password))->toBeTrue();
});
