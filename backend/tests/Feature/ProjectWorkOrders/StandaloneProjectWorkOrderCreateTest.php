<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0431: a project commessa born without any offer, created through
 * POST /api/project-work-orders, carries its own client, company, sites and
 * business-function lines (AC-001..AC-004).
 */
function standaloneCreator(): User
{
    $user = workOrderUserWith(['create', 'view']);
    $user->givePermissionTo(Permission::findOrCreate('project-work-orders.view', 'web'));

    return $user;
}

it('creates a project commessa without an offer, with its own references (AC-001)', function () {
    Sanctum::actingAs(standaloneCreator());
    $payload = standaloneProjectPayload();

    $response = $this->postJson('/api/project-work-orders', $payload)->assertCreated();

    $workOrder = WorkOrder::query()->findOrFail($response->json('data.id'));
    expect($workOrder->is_standalone)->toBeTrue()
        ->and($workOrder->quote_id)->toBeNull()
        ->and($workOrder->type->value)->toBe('project')
        ->and($workOrder->code)->toStartWith('COM-')
        ->and($workOrder->productLines)->toHaveCount(1);

    $response
        ->assertJsonPath('data.is_standalone', true)
        ->assertJsonPath('data.is_multi_quote', false)
        ->assertJsonPath('data.title', 'Progetto interno')
        ->assertJsonPath('data.registry.id', $payload['registry_id'])
        ->assertJsonPath('data.company.id', $payload['company_id'])
        ->assertJsonPath('data.company_site.id', $payload['company_site_id'])
        ->assertJsonPath('data.operational_site.id', $payload['operational_site_id'])
        ->assertJsonPath('data.product_lines.0.product_category.id', $payload['product_lines'][0]['product_category_id'])
        ->assertJsonPath('data.quote', null)
        ->assertJsonPath('data.quote_lines', []);
    expect($response->json('data.client_info'))->not->toBeNull();
});

it('rejects a commessa without client, company, business functions or title (AC-002)', function (string $missing) {
    Sanctum::actingAs(standaloneCreator());
    $payload = standaloneProjectPayload();
    unset($payload[$missing]);

    $this->postJson('/api/project-work-orders', $payload)->assertStatus(422)->assertJsonValidationErrors($missing);

    expect(WorkOrder::count())->toBe(0);
})->with(['registry_id', 'company_id', 'product_lines', 'title']);

it('rejects an empty business-function list (AC-002)', function () {
    Sanctum::actingAs(standaloneCreator());

    $this->postJson('/api/project-work-orders', standaloneProjectPayload(['product_lines' => []]))
        ->assertStatus(422)->assertJsonValidationErrors('product_lines');
});

it('rejects a company site of another company (AC-002)', function () {
    Sanctum::actingAs(standaloneCreator());
    $otherSite = CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->postJson('/api/project-work-orders', standaloneProjectPayload(['company_site_id' => $otherSite->id]))
        ->assertStatus(422)->assertJsonValidationErrors('company_site_id');
});

it('accepts a commessa without sites (AC-002)', function () {
    Sanctum::actingAs(standaloneCreator());

    $this->postJson('/api/project-work-orders', standaloneProjectPayload(['company_site_id' => null, 'operational_site_id' => null]))
        ->assertCreated()
        ->assertJsonPath('data.company_site', null)
        ->assertJsonPath('data.operational_site', null);
});

it('ignores an offer, lines or a type sent along: always project, never linked (AC-001)', function () {
    Sanctum::actingAs(standaloneCreator());

    $response = $this->postJson('/api/project-work-orders', standaloneProjectPayload(['type' => 'processing', 'quote_id' => 999, 'quote_line_ids' => [1]]))
        ->assertCreated();

    $workOrder = WorkOrder::query()->findOrFail($response->json('data.id'));
    expect($workOrder->type->value)->toBe('project')
        ->and($workOrder->quote_id)->toBeNull()
        ->and($workOrder->quoteLines()->count())->toBe(0);
});

it('is 403 without work-orders.create (AC-003)', function () {
    Sanctum::actingAs(workOrderUserWith(['view']));

    $this->postJson('/api/project-work-orders', standaloneProjectPayload())->assertForbidden();

    expect(WorkOrder::count())->toBe(0);
});

it('is not a multi-offer commessa, while a commessa with no offer and no flag still is (AC-004)', function () {
    $standalone = standaloneProjectWorkOrder();
    $multiQuote = WorkOrder::factory()->create(['quote_id' => null]);

    expect($standalone->isMultiQuote())->toBeFalse()
        ->and($multiQuote->isMultiQuote())->toBeTrue();
});
