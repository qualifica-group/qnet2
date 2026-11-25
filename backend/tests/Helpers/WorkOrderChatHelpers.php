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
     * A user who only holds the `view` of both commesse modules and sits in the
     * team of $workOrder as a Responsabile ('supervisors') or Partecipante
     * ('participants'). REQUIREMENT CHANGED (2026-11-25): a project commessa
     * opens with `project-work-orders.view`.
     */
    function chatMember(WorkOrder $workOrder, string $team = 'supervisors'): User
    {
        $permissions = ['work-orders.view', 'project-work-orders.view'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
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
        $permissions = ['work-orders.view', 'work-orders.viewAll', 'project-work-orders.view'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

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
