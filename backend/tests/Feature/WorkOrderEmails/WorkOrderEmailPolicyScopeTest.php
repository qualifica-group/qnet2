<?php

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| WorkOrderPolicy::viewEmails/sendEmail — spec 0175, D-14
|--------------------------------------------------------------------------
|
| Both abilities require the resource permission AND the SAME membership
| scoping as view/update/delete (WorkOrderVisibilityScope) — a permission
| holder outside the commessa's team is still refused, unless they also hold
| work-orders.viewAll.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['view', 'viewAll', 'viewEmails', 'sendEmail'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
});

it('D-14: a supervisor with viewEmails/sendEmail passes both abilities on their own commessa', function () {
    $supervisor = User::factory()->create();
    $supervisor->givePermissionTo(['work-orders.view', 'work-orders.viewEmails', 'work-orders.sendEmail']);
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach($supervisor->id);

    expect($supervisor->can('viewEmails', $workOrder))->toBeTrue()
        ->and($supervisor->can('sendEmail', $workOrder))->toBeTrue();
});

it('D-14: the same permissions are refused on a commessa the actor does not belong to, without viewAll', function () {
    $outsider = User::factory()->create();
    $outsider->givePermissionTo(['work-orders.view', 'work-orders.viewEmails', 'work-orders.sendEmail']);
    $workOrder = WorkOrder::factory()->create();

    expect($outsider->can('viewEmails', $workOrder))->toBeFalse()
        ->and($outsider->can('sendEmail', $workOrder))->toBeFalse();
});

it('D-14: work-orders.viewAll lifts the scoping, mirroring view/update/delete', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo(['work-orders.view', 'work-orders.viewAll', 'work-orders.viewEmails', 'work-orders.sendEmail']);
    $workOrder = WorkOrder::factory()->create();

    expect($manager->can('viewEmails', $workOrder))->toBeTrue()
        ->and($manager->can('sendEmail', $workOrder))->toBeTrue();
});

it('D-14: a team member without the permission is refused despite being in scope', function () {
    $supervisor = User::factory()->create();
    $supervisor->givePermissionTo('work-orders.view');
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach($supervisor->id);

    expect($supervisor->can('viewEmails', $workOrder))->toBeFalse()
        ->and($supervisor->can('sendEmail', $workOrder))->toBeFalse();
});
