<?php

use App\Models\User;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0081, AC-020: the grant that decides who is copied on the transfer
 * notifications exists in the catalogue and is bound to the `supervisor`
 * role by the seed.
 *
 * Worth its own test even though the binding is implicit (the role takes the
 * whole catalogue minus its denied resources): the assertion is what makes it
 * a DECISION rather than a side effect nobody would notice breaking.
 */
uses(RefreshDatabase::class);

it('permissions:sync creates the transfer-notification grant (AC-020)', function () {
    Artisan::call('permissions:sync');

    expect(Permission::query()->where('name', 'request-management.receiveTransferNotifications')->exists())->toBeTrue();
});

it('the seeded supervisor role holds the transfer-notification grant (AC-020)', function () {
    $this->seed(TestUsersSeeder::class);

    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();

    expect($supervisor->can('request-management.receiveTransferNotifications'))->toBeTrue();
});
