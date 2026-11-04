<?php

use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;

// Shared by tests/Feature/ProjectWorkOrders (spec 0352, B4).

if (! function_exists('projectWorkOrderViewer')) {
    /**
     * A user who can list/open and export work orders ($viewAll: sees every
     * one, otherwise only the ones they belong to).
     */
    function projectWorkOrderViewer(bool $viewAll = true): User
    {
        $user = User::factory()->create();

        foreach (['viewAny', 'view', 'export', ...($viewAll ? ['viewAll'] : [])] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("work-orders.{$ability}", 'web'));
        }

        return $user;
    }
}

if (! function_exists('typedWorkOrder')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function typedWorkOrder(WorkOrderType $type, array $attributes = []): WorkOrder
    {
        return WorkOrder::factory()->create([...$attributes, 'type' => $type]);
    }
}
