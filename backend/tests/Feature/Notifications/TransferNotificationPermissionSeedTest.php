<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaOperatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Decisione utente 2026-09-29 (REQUIREMENT CHANGE of spec 0081 AC-020,
 * declared): the supervisory copy of a transfer goes to whoever holds the
 * module's `viewAll`. The dedicated `receiveTransferNotifications` grant is
 * gone — every role scoped to its own requests inherited it with the whole
 * module, so every operator was copied on every transfer.
 *
 * Asserted on the real seed: the audience is a DECISION bound to the roles,
 * not a side effect nobody would notice breaking.
 */
uses(RefreshDatabase::class);

it('permissions:sync no longer creates the dedicated transfer-notification grant', function () {
    Artisan::call('permissions:sync');

    expect(Permission::query()->where('name', 'like', '%.receiveTransferNotifications')->exists())->toBeFalse();
});

it('the prune migration drops the old grant of both modules and its role bindings, leaving viewAll alone', function () {
    $role = Role::findOrCreate('supervisor');
    $role->givePermissionTo([
        Permission::findOrCreate('request-management.receiveTransferNotifications'),
        Permission::findOrCreate('enrollee-management.receiveTransferNotifications'),
        Permission::findOrCreate('request-management.viewAll'),
    ]);

    (require database_path('migrations/2026_09_29_120000_prune_receive_transfer_notifications_permissions.php'))->up();

    expect(Permission::query()->where('name', 'like', '%.receiveTransferNotifications')->exists())->toBeFalse()
        ->and(DB::table('role_has_permissions')->where('role_id', $role->id)->count())->toBe(1)
        ->and(Permission::query()->where('name', 'request-management.viewAll')->exists())->toBeTrue();
});

it('the seeded supervisor sees every request, and so receives the supervisory copy', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();

    expect($supervisor->can('request-management.viewAll'))->toBeTrue();
});

it('a seeded commercial operator sees only their own requests, and so is not in the supervisory audience', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $operator = User::query()->where('email', 'gaetano.dellaporta@qualificagroup.com')->firstOrFail();

    expect($operator->can('request-management.viewAll'))->toBeFalse();
});
