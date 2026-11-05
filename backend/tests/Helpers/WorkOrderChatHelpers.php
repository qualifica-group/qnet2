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
    /** Force closes the work order (computed status `closed`). */
    function chatClose(WorkOrder $workOrder): WorkOrder
    {
        $workOrder->forceFill(['is_force_closed' => true])->save();

        return $workOrder;
    }
}

if (! function_exists('chatComplete')) {
    /** Brings the work order to 100% progress (computed status `completed`). */
    function chatComplete(WorkOrder $workOrder): WorkOrder
    {
        $workOrder->progressEntries()->create([
            'percentage' => 100,
            'recorded_on' => now()->subDay()->toDateString(),
            'created_by' => User::factory()->create()->id,
        ]);

        return $workOrder;
    }
}

if (! function_exists('chatUrl')) {
    function chatUrl(WorkOrder $workOrder, string $suffix = 'messages'): string
    {
        return "/api/work-orders/{$workOrder->id}/chat/{$suffix}";
    }
}
