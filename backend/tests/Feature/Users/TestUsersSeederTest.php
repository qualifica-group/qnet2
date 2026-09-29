<?php

use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

// Since the user directive 2026-09-15 the seeder carries the super-admin alone:
// the tester roster gave way to the real operators (QualificaOperatorSeeder).
// User directive 2026-09-29: Nicola Eliseo is a super-admin too; Mario
// Esposito and Enrico Ferrante hold the `admin` role, every permission of the
// catalogue without being super-admin.
uses(RefreshDatabase::class);

const SUPER_ADMIN_EMAIL = 'ciro.cacciapuoti@qualificagroup.com';

it('creates the privileged accounts only, standalone on a fresh database', function () {
    $this->seed(TestUsersSeeder::class);

    $user = User::query()->where('email', SUPER_ADMIN_EMAIL)->firstOrFail();

    expect($user->getRoleNames()->all())->toBe(['super-admin'])
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Qualifica2026!', $user->password))->toBeTrue()
        ->and($user->name)->toBe('Ciro Cacciapuoti')
        ->and($user->personalData->first_name)->toBe('Ciro')
        ->and($user->personalData->last_name)->toBe('Cacciapuoti')
        ->and(User::query()->count())->toBe(4)
        ->and(User::role('super-admin')->orderBy('email')->pluck('email')->all())->toBe([
            SUPER_ADMIN_EMAIL,
            'nicola.eliseo@qualificagroup.com',
        ])
        ->and(User::role(OperatorRoleCatalogue::ADMIN_ROLE)->orderBy('email')->pluck('email')->all())->toBe([
            'enrico.ferrante@qualificagroup.com',
            'mario.esposito@qualificagroup.com',
        ]);
});

it('gives the admin accounts every permission of the catalogue, without the privileged role', function () {
    $this->seed(TestUsersSeeder::class);

    $admin = User::query()->where('email', 'mario.esposito@qualificagroup.com')->sole();

    expect($admin->getRoleNames()->all())->toBe([OperatorRoleCatalogue::ADMIN_ROLE])
        ->and($admin->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(Permission::query()->pluck('name')->sort()->values()->all())
        ->and($admin->can('request-management.viewAll'))->toBeTrue()
        ->and($admin->can('work-orders.viewAll'))->toBeTrue();
});

it('is idempotent and restores the shared password on a re-run', function () {
    $this->seed(TestUsersSeeder::class);

    User::query()->where('email', SUPER_ADMIN_EMAIL)->firstOrFail()
        ->forceFill(['password' => Hash::make('changed-by-hand')])->save();

    $this->seed(TestUsersSeeder::class);

    $user = User::query()->where('email', SUPER_ADMIN_EMAIL)->sole();

    expect(Hash::check('Qualifica2026!', $user->password))->toBeTrue();
});
