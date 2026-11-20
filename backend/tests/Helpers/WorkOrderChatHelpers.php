<?php

declare(strict_types=1);

use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

if (! function_exists('chatWorkOrder')) {
    /**
     * A work order of type project (the only kind with a chat), spec 0359.
     *
     * @param  array<string, mixed>  $attributes
     */
    function chatWorkOrder(array $attributes = []): WorkOrder
    {
        return WorkOrder::factory()->create(['type' => WorkOrderType::Project, ...$attributes]);
    }
}

if (! function_exists('chatMember')) {
    /**
     * A user who only holds `work-orders.view` and sits in the team of $workOrder
     * as a Responsabile ('supervisors') or Partecipante ('participants').
     */
    function chatMember(WorkOrder $workOrder, string $team = 'supervisors'): User
    {
        Permission::findOrCreate('work-orders.view');

        $user = User::factory()->create();
        $user->givePermissionTo('work-orders.view');
        $team === 'participants'
            ? $workOrder->participants()->attach($user->id, ['position' => $workOrder->participants()->count() + 1])
            : $workOrder->supervisors()->attach($user->id);

        return $user;
    }
}

if (! function_exists('chatOutsider')) {
    /**
     * A user who sees every work order (`viewAll`) without being in any team.
     */
    function chatOutsider(): User
    {
        foreach (['view', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['work-orders.view', 'work-orders.viewAll']);

        return $user;
    }
}

if (! function_exists('chatSuperAdmin')) {
    function chatSuperAdmin(): User
    {
        Role::findOrCreate('super-admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }
}

if (! function_exists('chatClose')) {
    /** Force closes the work order with a negative outcome (phase `cancelled`, spec 0395). */
    function chatClose(WorkOrder $workOrder): WorkOrder
    {
        $workOrder->forceFill(['is_force_closed' => true, 'work_phase' => 'cancelled'])->save();

        return $workOrder;
    }
}

if (! function_exists('chatComplete')) {
    /** Completes the work order (phase `completed`, spec 0395: REQUIREMENT CHANGED, no longer 100% progress). */
    function chatComplete(WorkOrder $workOrder): WorkOrder
    {
        $workOrder->forceFill(['work_phase' => 'completed'])->save();

        return $workOrder;
    }
}

if (! function_exists('chatUrl')) {
    function chatUrl(WorkOrder $workOrder, string $suffix = 'messages'): string
    {
        return "/api/work-orders/{$workOrder->id}/chat/{$suffix}";
    }
}
