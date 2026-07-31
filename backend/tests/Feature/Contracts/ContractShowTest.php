<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * GET /api/contracts/{contract} (spec 0072, MT-02): the full read-only
 * projection off `quote` (D-1), 403/404, and the anti-duplication assertion
 * (AC-040).
 */
uses(RefreshDatabase::class);

if (! function_exists('contractShowUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractShowUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'schedule', 'changeStatus', 'reactivate'] as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("contracts.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('contractShowFullContract')) {
    /**
     * A contract whose quote carries every relation the data_contract
     * projects (registry via opportunity, company/site, operational site,
     * the 3 commercial roles, payment method, non-zero aggregates), so the
     * GET response exercises every projection branch.
     */
    function contractShowFullContract(): Contract
    {
        $registry = Registry::factory()->create();
        $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
        $company = Company::factory()->create();
        $companySite = CompanySite::factory()->create(['company_id' => $company->id]);
        $operationalSite = OperationalSite::factory()->withAddress()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $commercial = Referent::factory()->create();
        $reporter = Referent::factory()->create();
        $supervisor = User::factory()->create();

        $quote = Quote::factory()->create([
            'opportunity_id' => $opportunity->id,
            'company_id' => $company->id,
            'company_site_id' => $companySite->id,
            'operational_site_id' => $operationalSite->id,
            'payment_method_id' => $paymentMethod->id,
            'commercial_id' => $commercial->id,
            'reporter_id' => $reporter->id,
            'supervisor_id' => $supervisor->id,
            'revenue_net' => '1000.00',
            'revenue_vat' => '220.00',
            'cost_net' => '400.00',
            'cost_vat' => '88.00',
            'margin_net' => '600.00',
        ]);

        return Contract::factory()->for($quote)->create();
    }
}

// ---------------------------------------------------------------------------
// Happy path — full projection off `quote` (D-1)
// ---------------------------------------------------------------------------

it('GET shows the full contract, projecting client/opportunity/amounts from the quote', function () {
    $contract = contractShowFullContract();
    $actor = contractShowUserWith(['view']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/contracts/{$contract->id}")->assertOk();

    $response
        ->assertJsonPath('data.id', $contract->id)
        ->assertJsonPath('data.quote_id', $contract->quote_id)
        ->assertJsonPath('data.quote.code', $contract->quote->code)
        ->assertJsonPath('data.quote.title', $contract->quote->title)
        ->assertJsonPath('data.quote.revenue_net', '1000.00')
        ->assertJsonPath('data.quote.revenue_vat', '220.00')
        ->assertJsonPath('data.quote.revenue_gross', '1220.00')
        ->assertJsonPath('data.quote.cost_net', '400.00')
        ->assertJsonPath('data.quote.margin_net', '600.00')
        ->assertJsonPath('data.registry.id', $contract->quote->opportunity->registry->id)
        ->assertJsonPath('data.opportunity.id', $contract->quote->opportunity_id)
        ->assertJsonPath('data.company.name', $contract->quote->company->denomination)
        ->assertJsonPath('data.company_site.id', $contract->quote->company_site_id)
        ->assertJsonPath('data.operational_site.id', $contract->quote->operational_site_id)
        ->assertJsonPath('data.commercial.id', $contract->quote->commercial_id)
        ->assertJsonPath('data.reporter.id', $contract->quote->reporter_id)
        ->assertJsonPath('data.supervisor.id', $contract->quote->supervisor_id)
        ->assertJsonPath('data.payment_method.id', $contract->quote->payment_method_id)
        ->assertJsonPath('data.contract_status_id', $contract->contract_status_id)
        ->assertJsonPath('data.contract_status.id', $contract->contractStatus->id)
        ->assertJsonPath('data.is_suspended', false)
        ->assertJsonPath('data.status_before_suspension', null)
        ->assertJsonPath('data.summary.revenue.net', '1000.00')
        ->assertJsonPath('data.summary.revenue.gross', '1220.00')
        ->assertJsonPath('data.summary.cost.net', '400.00')
        ->assertJsonPath('data.summary.margin.net', '600.00');

    expect($response->json('data.offer_lines'))->toBe([])
        ->and($response->json('permissions.resource'))->toBeArray()
        ->and($response->json('permissions.actions'))->toHaveKey('reactivate');
});

it('a suspended contract exposes its status_before_suspension and is_suspended=true', function () {
    $contract = contractShowFullContract();
    $previousStatus = $contract->contractStatus;
    $suspendedStatus = ContractStatus::factory()->create();
    $contract->forceFill([
        'contract_status_id' => $suspendedStatus->id,
        'status_before_suspension_id' => $previousStatus->id,
        'suspended_at' => now(),
    ])->save();
    $actor = contractShowUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('data.is_suspended', true)
        ->assertJsonPath('data.status_before_suspension.id', $previousStatus->id);
});

// ---------------------------------------------------------------------------
// 403 / 404
// ---------------------------------------------------------------------------

it('GET is 403 without contracts.view', function () {
    $contract = Contract::factory()->create();
    $actor = contractShowUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/contracts/{$contract->id}")->assertForbidden();
});

it('GET a nonexistent contract is 404', function () {
    $actor = contractShowUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/contracts/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// AC-040 — no duplicated quote data on `contracts` itself
// ---------------------------------------------------------------------------

it('AC-040: contracts has no column duplicating the quote it belongs to', function () {
    $columns = Schema::getColumnListing('contracts');

    expect($columns)->not->toContain('code')
        ->and($columns)->not->toContain('title')
        ->and($columns)->not->toContain('registry_id')
        ->and($columns)->not->toContain('opportunity_id')
        ->and($columns)->not->toContain('company_id')
        ->and($columns)->not->toContain('revenue_net')
        ->and($columns)->not->toContain('revenue_vat')
        ->and($columns)->not->toContain('cost_net')
        ->and($columns)->not->toContain('margin_net');

    $contract = contractShowFullContract();
    $actor = contractShowUserWith(['view']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/contracts/{$contract->id}")->assertOk();

    // Everything IS present in the response, just projected from `quote`'s
    // own relations, never from a `contracts` column.
    expect($response->json('data.company.name'))->toBe($contract->quote->company->denomination)
        ->and($response->json('data.opportunity.id'))->toBe($contract->quote->opportunity_id)
        ->and($response->json('data.quote.revenue_net'))->toBe('1000.00');
});

// ---------------------------------------------------------------------------
// No N+1 (Model::preventLazyLoading, mirrors ProductCrudTest's own gate)
// ---------------------------------------------------------------------------

it('GET show triggers no lazy loading on the eager-loaded tree', function () {
    $contract = contractShowFullContract();
    $actor = contractShowUserWith(['view']);
    Sanctum::actingAs($actor);

    Contract::preventLazyLoading();

    $this->getJson("/api/contracts/{$contract->id}")->assertOk();

    Contract::preventLazyLoading(false);
});
