<?php

use App\Enums\WorkOrderType;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\Registry;
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

if (! function_exists('standaloneProjectPayload')) {
    /**
     * A valid POST /api/project-work-orders body (spec 0431): own client,
     * company with one of its sites, an operational site and one business
     * function line.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function standaloneProjectPayload(array $overrides = []): array
    {
        $company = Company::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);

        return [
            ...workOrderRequiredFields(),
            'title' => 'Progetto interno',
            'registry_id' => Registry::factory()->create()->id,
            'company_id' => $company->id,
            'company_site_id' => CompanySite::factory()->create(['company_id' => $company->id])->id,
            'operational_site_id' => OperationalSite::factory()->create()->id,
            'product_lines' => [['product_category_id' => $category->id]],
            ...$overrides,
        ];
    }
}

if (! function_exists('standaloneProjectWorkOrder')) {
    /**
     * A project commessa born without an offer (spec 0431), with its own
     * client, company, sites and one business-function line.
     *
     * @param  array<string, mixed>  $attributes
     */
    function standaloneProjectWorkOrder(array $attributes = []): WorkOrder
    {
        $company = Company::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'quote_id' => null,
            'type' => WorkOrderType::Project,
            'registry_id' => Registry::factory()->create()->id,
            'company_id' => $company->id,
            'company_site_id' => CompanySite::factory()->create(['company_id' => $company->id])->id,
            'operational_site_id' => OperationalSite::factory()->create()->id,
            ...$attributes,
        ]);
        $workOrder->forceFill(['is_standalone' => true])->save();
        $function = BusinessFunction::factory()->create();
        $workOrder->productLines()->create([
            'business_function_id' => $function->id,
            'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $function->id])->id,
        ]);

        return $workOrder->refresh();
    }
}
