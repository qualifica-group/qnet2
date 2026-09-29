<?php

use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Database\Seeders\QualificaCatalog\StaffRoster;
use Database\Seeders\QualificaOperatorSeeder;
use Database\Seeders\QualificaStaffSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

// Spec 0177, user directive 2026-09-29: every account of the production seed
// that receives the shared password must replace it at first access, unless
// `seeding.force_password_change` is off (staging).
uses(RefreshDatabase::class);

it('AC-022: flags the privileged accounts on every run, since every run restores the shared password', function (): void {
    config(['seeding.force_password_change' => true]);
    Notification::fake();

    test()->seed(TestUsersSeeder::class);

    Notification::assertNothingSent();
    expect(User::query()->where('must_set_password', false)->count())->toBe(0)
        ->and(User::query()->count())->toBe(count(TestUsersSeeder::TEST_USERS));

    $user = User::query()->where('email', TestUsersSeeder::TEST_USERS[0]['email'])->sole();
    $user->forceFill(['password' => Hash::make('changed-by-hand'), 'must_set_password' => false])->save();

    test()->seed(TestUsersSeeder::class);

    $user->refresh();

    expect(Hash::check((string) config('seeding.password'), $user->password))->toBeTrue()
        ->and($user->must_set_password)->toBeTrue();
});

it('AC-022: flags a new operator, and leaves the flag of an existing one alone on a re-run', function (): void {
    config(['seeding.force_password_change' => true]);

    test()->seed(QualificaOperatorSeeder::class);

    expect(User::query()->where('must_set_password', true)->count())->toBe(count(OperatorRoster::OPERATORS));

    $operator = User::query()->where('email', OperatorRoster::OPERATORS[0][2])->sole();
    $operator->forceFill(['password' => Hash::make('changed-by-hand'), 'must_set_password' => false])->save();

    test()->seed(QualificaOperatorSeeder::class);

    expect($operator->fresh()->must_set_password)->toBeFalse();
});

it('AC-022: flags a new staff account, and never an account that already existed', function (): void {
    config(['seeding.force_password_change' => true]);

    $existing = User::factory()->create(['email' => StaffRoster::USERS[0][2]]);

    test()->seed(QualificaStaffSeeder::class);

    expect(User::query()->where('must_set_password', true)->count())->toBe(count(StaffRoster::USERS) - 1)
        ->and($existing->fresh()->must_set_password)->toBeFalse();
});

it('AC-022: flags nobody when the forced change is off (staging)', function (): void {
    config(['seeding.force_password_change' => false]);

    test()->seed(TestUsersSeeder::class);
    test()->seed(QualificaStaffSeeder::class);

    expect(User::query()->count())->toBe(count(TestUsersSeeder::TEST_USERS) + count(StaffRoster::USERS))
        ->and(User::query()->where('must_set_password', true)->exists())->toBeFalse();
});
