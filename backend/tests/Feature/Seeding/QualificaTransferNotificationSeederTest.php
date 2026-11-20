<?php

use App\Models\Role;
use App\Models\User;
use App\Services\RequestManagement\RequestTransferService;
use Database\Seeders\QualificaTransferNotificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([...QualificaTransferNotificationSeeder::PERMISSIONS, 'request-management.viewAll'] as $permission) {
        Permission::findOrCreate($permission);
    }
});

it('0081 rev. 2026-11-12: gives the transfer notification role to the chosen supervisors only, on top of their roles', function () {
    $supervisorRole = Role::findOrCreate('supervisore-commerciale', 'web');
    $supervisorRole->givePermissionTo('request-management.viewAll');
    $recipients = collect(QualificaTransferNotificationSeeder::RECIPIENT_EMAILS)
        ->map(fn (string $email): User => User::factory()->create(['email' => $email])->assignRole($supervisorRole));
    $other = User::factory()->create()->assignRole($supervisorRole);

    $this->seed(QualificaTransferNotificationSeeder::class);

    $role = Role::findByName(RequestTransferService::NOTIFIED_ROLE);
    expect($role->description)->toBe(QualificaTransferNotificationSeeder::DESCRIPTION)
        ->and($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(QualificaTransferNotificationSeeder::PERMISSIONS)->sort()->values()->all())
        ->and(User::role(RequestTransferService::NOTIFIED_ROLE)->pluck('id')->sort()->values()->all())
        ->toBe($recipients->pluck('id')->sort()->values()->all())
        ->and($other->fresh()->hasRole(RequestTransferService::NOTIFIED_ROLE))->toBeFalse()
        // Nobody loses what they see: viewAll stays where it was.
        ->and($other->fresh()->can('request-management.viewAll'))->toBeTrue();

    $recipients->each(fn (User $user) => expect($user->fresh()->hasRole('supervisore-commerciale'))->toBeTrue());
});

it('0081 rev. 2026-11-12: a re-run converges and a missing account is skipped', function () {
    User::factory()->create(['email' => QualificaTransferNotificationSeeder::RECIPIENT_EMAILS[0]]);

    $this->seed(QualificaTransferNotificationSeeder::class);
    $this->seed(QualificaTransferNotificationSeeder::class);

    expect(Role::query()->where('name', RequestTransferService::NOTIFIED_ROLE)->count())->toBe(1)
        ->and(User::role(RequestTransferService::NOTIFIED_ROLE)->count())->toBe(1);
});
