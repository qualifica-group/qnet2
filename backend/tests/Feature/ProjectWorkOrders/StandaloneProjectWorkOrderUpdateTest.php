<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0431 (AC-005): the own client, company, sites and business-function
 * lines of a commessa born without an offer are edited from its detail; on a
 * commessa born from an offer the same keys are refused (the offer owns them).
 */
function standaloneEditor(): User
{
    $user = workOrderUserWith(['update', 'view']);
    $user->givePermissionTo(Permission::findOrCreate('project-work-orders.view', 'web'));

    return $user;
}

it('updates the own client, company, sites and lines of a commessa without an offer', function () {
    Sanctum::actingAs(standaloneEditor());
    $workOrder = standaloneProjectWorkOrder();
    $company = Company::factory()->create();
    $site = CompanySite::factory()->create(['company_id' => $company->id]);
    $registry = Registry::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'registry_id' => $registry->id,
        'company_id' => $company->id,
        'company_site_id' => $site->id,
        'operational_site_id' => null,
        'product_lines' => [['product_category_id' => $category->id]],
    ])->assertOk()
        ->assertJsonPath('data.registry.id', $registry->id)
        ->assertJsonPath('data.company.id', $company->id)
        ->assertJsonPath('data.company_site.id', $site->id)
        ->assertJsonPath('data.operational_site', null)
        ->assertJsonPath('data.product_lines.0.product_category.id', $category->id);

    expect($workOrder->productLines()->count())->toBe(1);
});

it('refuses a company change that leaves the current company site behind', function () {
    Sanctum::actingAs(standaloneEditor());
    $workOrder = standaloneProjectWorkOrder();

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['company_id' => Company::factory()->create()->id])
        ->assertStatus(422)->assertJsonValidationErrors('company_site_id');
});

it('refuses to clear the client or the business functions', function (string $key, mixed $value) {
    Sanctum::actingAs(standaloneEditor());
    $workOrder = standaloneProjectWorkOrder();

    $this->patchJson("/api/work-orders/{$workOrder->id}", [$key => $value])
        ->assertStatus(422)->assertJsonValidationErrors($key);
})->with([
    'client' => ['registry_id', null],
    'company' => ['company_id', null],
    'lines' => ['product_lines', []],
]);

it('keeps a commessa without an offer a project one (D-5)', function () {
    Sanctum::actingAs(standaloneEditor());
    $workOrder = standaloneProjectWorkOrder();

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['type' => 'processing'])
        ->assertStatus(422)->assertJsonValidationErrors('type');

    expect($workOrder->refresh()->type->value)->toBe('project');
});

it('refuses the own references on a commessa born from an offer: the offer owns them', function (string $key) {
    Sanctum::actingAs(standaloneEditor());
    $workOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create()->id]);

    $this->patchJson("/api/work-orders/{$workOrder->id}", [$key => 1])
        ->assertStatus(422)->assertJsonValidationErrors([$key => __('work_orders.references_owned_by_offer')]);
})->with(['registry_id', 'company_id', 'company_site_id', 'operational_site_id', 'product_lines']);

it('reads the client from the offer on a commessa born from one', function () {
    Sanctum::actingAs(standaloneEditor());
    $quote = Quote::factory()->create();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.is_standalone', false)
        ->assertJsonPath('data.registry.id', $quote->opportunity->registry_id);
});

it('offers no proforma request on a commessa without an offer (D-9)', function () {
    $user = standaloneEditor();
    $user->givePermissionTo(Permission::findOrCreate('proforma-requests.create', 'web'));
    Sanctum::actingAs($user);
    $workOrder = standaloneProjectWorkOrder();

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.request_proforma', false);
});

it('answers the contract data of a work order without an offer: no lines, no error', function () {
    $user = workOrderPaymentsUserWith(['viewContractData']);
    $user->givePermissionTo(Permission::findOrCreate('project-work-orders.view', 'web'));
    Sanctum::actingAs($user);
    $workOrder = standaloneProjectWorkOrder();

    $this->getJson("/api/work-orders/{$workOrder->id}/contract-data")
        ->assertOk()
        ->assertJsonPath('data.lines', []);
});

it('answers the proforma summary of a work order without an offer: no payment method, no error', function () {
    $user = standaloneEditor();
    $user->givePermissionTo(Permission::findOrCreate('proforma-requests.create', 'web'));
    Sanctum::actingAs($user);
    $workOrder = standaloneProjectWorkOrder();

    $this->getJson("/api/work-orders/{$workOrder->id}/proforma-requests/summary")->assertOk();
});
