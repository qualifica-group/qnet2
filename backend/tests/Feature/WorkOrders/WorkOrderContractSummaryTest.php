<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
 * The Commessa detail names its Contratto, not the underlying offer (user
 * directive 2026-09-16): WorkOrderResource exposes `contract` as the
 * `{ id, code, title, expiry_date }` of the Contract born from the linked quote, `null`
 * while that quote has no contract.
 */

function contractSummaryActor(): User
{
    foreach (['view', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['work-orders.view', 'work-orders.viewAll']);

    return $user;
}

it('exposes the contract of the linked quote as { id, code, title, expiry_date }', function () {
    $quote = Quote::factory()->create();
    $contract = Contract::factory()->create(['quote_id' => $quote->id, 'expiry_date' => '2027-03-31']);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs(contractSummaryActor());

    $response = $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk();

    expect($response->json('data.contract'))->toBe([
        'id' => $contract->id,
        'code' => $quote->code,
        'title' => $quote->title,
        'expiry_date' => '2027-03-31',
    ]);
});

it('exposes a null contract while the linked quote has none', function () {
    $quote = Quote::factory()->create();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs(contractSummaryActor());

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.contract', null);
});

it('exposes the company, company site and operational site of the linked quote', function () {
    $company = Company::factory()->create();
    $companySite = CompanySite::factory()->create(['company_id' => $company->id]);
    $operationalSite = OperationalSite::factory()->withAddress()->create();
    $quote = Quote::factory()->create([
        'company_id' => $company->id,
        'company_site_id' => $companySite->id,
        'operational_site_id' => $operationalSite->id,
    ]);
    Contract::factory()->create(['quote_id' => $quote->id]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs(contractSummaryActor());

    $response = $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk();

    expect($response->json('data.company'))->toBe(['id' => $company->id, 'name' => $company->denomination])
        ->and($response->json('data.company_site'))->toBe(['id' => $companySite->id, 'name' => $companySite->name])
        ->and($response->json('data.operational_site.id'))->toBe($operationalSite->id)
        ->and($response->json('data.operational_site.label'))->not->toBe('');
});

it('exposes null company and sites when the linked quote has none', function () {
    $quote = Quote::factory()->create(['company_id' => null, 'company_site_id' => null, 'operational_site_id' => null]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs(contractSummaryActor());

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.company', null)
        ->assertJsonPath('data.company_site', null)
        ->assertJsonPath('data.operational_site', null);
});
