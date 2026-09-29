<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;

if (! function_exists('workOrderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        $user->givePermissionTo('work-orders.viewAll');

        return $user;
    }
}

if (! function_exists('workOrderEmailActor')) {
    /**
     * A user holding the given work-orders abilities on ONE specific
     * commessa, WITHOUT work-orders.viewAll (unlike workOrderUserWith()
     * above) — spec 0175 BE-05's own membership-scope enforcement
     * (WorkOrderPolicy::viewEmails/sendEmail, D-14) needs actors that are
     * genuinely in or out of a commessa's team, not ones the blanket
     * viewAll permission would let through regardless.
     *
     * @param  array<int, string>  $abilities  e.g. ['viewEmails'], ['sendEmail']
     */
    function workOrderEmailActor(WorkOrder $workOrder, array $abilities, bool $inScope = true): User
    {
        foreach (['view', 'viewEmails', 'sendEmail', 'viewDocuments'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        if ($inScope) {
            $workOrder->supervisors()->attach($user->id);
        }

        return $user;
    }
}
