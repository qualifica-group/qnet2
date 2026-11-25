<?php

use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;

// Shared by tests/Feature/ProjectWorkOrders (spec 0359, B4).

if (! function_exists('projectWorkOrderViewer')) {
    /**
     * A user who can list/open and export work orders of both modules
     * ($viewAll: sees every one, otherwise only the ones they belong to).
     * REQUIREMENT CHANGED (2026-11-25): "Commesse di Progetto" has its own
     * `project-work-orders.viewAny|view`.
     */
    function projectWorkOrderViewer(bool $viewAll = true): User
    {
        $user = User::factory()->create();

        foreach (['viewAny', 'view', 'export', ...($viewAll ? ['viewAll'] : [])] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("work-orders.{$ability}", 'web'));
        }

        foreach (['viewAny', 'view'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("project-work-orders.{$ability}", 'web'));
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
