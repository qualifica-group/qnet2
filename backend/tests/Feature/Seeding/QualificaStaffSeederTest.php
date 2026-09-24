<?php

use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Database\Seeders\QualificaCatalog\StaffRoster;
use Database\Seeders\QualificaStaffSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

// The client's staff outside the mansionario (user directive 2026-09-24):
// created with the base role, never touching an account that already exists.
uses(RefreshDatabase::class);

it('creates every staff account with the base role, its anagrafica and the shared password', function (): void {
    test()->seed(QualificaStaffSeeder::class);

    expect(User::query()->count())->toBe(count(StaffRoster::USERS))
        ->and(User::role(OperatorRoleCatalogue::BASE_ROLE)->count())->toBe(count(StaffRoster::USERS));

    // An accented name, re-encoded from the ISO-8859-1 CSV.
    $user = User::query()->where('email', 'nicolo.caruso@qualificagroup.com')->with('personalData')->sole();

    expect($user->name)->toBe('Nicolò Caruso')
        ->and($user->getRoleNames()->all())->toBe([OperatorRoleCatalogue::BASE_ROLE])
        ->and($user->personalData->first_name)->toBe('Nicolò')
        ->and($user->personalData->last_name)->toBe('Caruso')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Qualifica2026!', $user->password))->toBeTrue();
});

it('never lists an account the operator roster or the super-admin already own', function (): void {
    $named = collect(OperatorRoster::OPERATORS)->pluck(2)
        ->merge(collect(TestUsersSeeder::TEST_USERS)->pluck('email'));
    $staff = collect(StaffRoster::USERS)->pluck(2);

    expect($staff->intersect($named)->all())->toBe([])
        ->and($staff->duplicates()->all())->toBe([]);
});

it('leaves an existing account untouched: name, role and password', function (): void {
    $existing = User::factory()->create([
        'email' => 'nicola.eliseo@qualificagroup.com',
        'name' => 'Nome scelto a mano',
        'password' => Hash::make('changed-by-hand'),
    ]);

    test()->seed(QualificaStaffSeeder::class);

    $existing->refresh();

    expect($existing->name)->toBe('Nome scelto a mano')
        ->and($existing->getRoleNames()->all())->toBe([])
        ->and(Hash::check('changed-by-hand', $existing->password))->toBeTrue()
        ->and($existing->personalData()->where('last_name', 'Eliseo')->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(count(StaffRoster::USERS));
});

it('is a no-op on a re-run, even for an account changed in between', function (): void {
    test()->seed(QualificaStaffSeeder::class);

    $user = User::query()->where('email', 'marco.esposito@qualificagroup.com')->firstOrFail();
    $user->syncRoles([OperatorRoleCatalogue::COMMERCIAL_ROLE]);

    test()->seed(QualificaStaffSeeder::class);

    expect(User::query()->count())->toBe(count(StaffRoster::USERS))
        ->and($user->fresh()->getRoleNames()->all())->toBe([OperatorRoleCatalogue::COMMERCIAL_ROLE]);
});
