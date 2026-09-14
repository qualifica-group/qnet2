<?php

use App\Models\TimeEntry;
use App\Models\User;
use App\Policies\TimeEntryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| TimeEntryPolicy record-level ownership rule (spec 0122, D-8)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
        Permission::findOrCreate("time-entries.{$ability}");
    }
});

it('grants view/update/delete to the owner holding the base permission, without manageAll', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo(['time-entries.view', 'time-entries.update', 'time-entries.delete']);
    $entry = TimeEntry::factory()->forUser($owner)->create();

    $policy = new TimeEntryPolicy;

    expect($policy->view($owner, $entry))->toBeTrue()
        ->and($policy->update($owner, $entry))->toBeTrue()
        ->and($policy->delete($owner, $entry))->toBeTrue();
});

it('denies view/update/delete on another user\'s entry without manageAll', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $other->givePermissionTo(['time-entries.view', 'time-entries.update', 'time-entries.delete']);
    $entry = TimeEntry::factory()->forUser($owner)->create();

    $policy = new TimeEntryPolicy;

    expect($policy->view($other, $entry))->toBeFalse()
        ->and($policy->update($other, $entry))->toBeFalse()
        ->and($policy->delete($other, $entry))->toBeFalse();
});

it('grants view/update/delete on another user\'s entry to a manageAll holder', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $admin->givePermissionTo(['time-entries.view', 'time-entries.update', 'time-entries.delete', 'time-entries.manageAll']);
    $entry = TimeEntry::factory()->forUser($owner)->create();

    $policy = new TimeEntryPolicy;

    expect($policy->view($admin, $entry))->toBeTrue()
        ->and($policy->update($admin, $entry))->toBeTrue()
        ->and($policy->delete($admin, $entry))->toBeTrue();
});

it('still requires the base permission even for the owner', function () {
    $owner = User::factory()->create(); // no permission granted
    $entry = TimeEntry::factory()->forUser($owner)->create();

    $policy = new TimeEntryPolicy;

    expect($policy->view($owner, $entry))->toBeFalse()
        ->and($policy->update($owner, $entry))->toBeFalse()
        ->and($policy->delete($owner, $entry))->toBeFalse();
});

it('exposes viewAny/create/export/exportMonthly/viewAll as plain resource-level permission checks', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['time-entries.viewAny', 'time-entries.create', 'time-entries.export', 'time-entries.exportMonthly', 'time-entries.viewAll']);

    $policy = new TimeEntryPolicy;

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->export($user))->toBeTrue()
        ->and($policy->exportMonthly($user))->toBeTrue()
        ->and($policy->viewAll($user))->toBeTrue();

    $stranger = User::factory()->create();

    expect($policy->viewAny($stranger))->toBeFalse()
        ->and($policy->create($stranger))->toBeFalse()
        ->and($policy->export($stranger))->toBeFalse()
        ->and($policy->exportMonthly($stranger))->toBeFalse()
        ->and($policy->viewAll($stranger))->toBeFalse();
});
