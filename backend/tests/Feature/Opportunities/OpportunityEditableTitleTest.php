<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0171: the opportunity title is editable from the form. A typed title is
 * kept and flagged `name_is_manual`; blank or equal to the automatic title
 * keeps the opportunity automatic (spec 0077 derivation from quoted products).
 */
uses(RefreshDatabase::class);

if (! function_exists('editableTitleActor')) {
    function editableTitleActor(string $ability): User
    {
        Permission::findOrCreate("opportunities.{$ability}");
        $actor = User::factory()->create();
        $actor->givePermissionTo("opportunities.{$ability}");

        return $actor;
    }
}

if (! function_exists('opportunityWithQuotedProduct')) {
    /** An automatic opportunity whose quote derives the title "OPP_{id} - ISO 9001". */
    function opportunityWithQuotedProduct(): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        nameDerivationNewQuoteWorkflowStatus();
        $product = nameDerivationRevenueProduct('ISO 9001');
        nameDerivationQuoteService()->create(
            nameDerivationCreateQuoteData($opportunity->id, [nameDerivationRevenueLine($product)]),
            nameDerivationActor(),
        );

        return $opportunity->fresh();
    }
}

it('AC-001: POST with a null name gets the automatic OPP_{id} title', function () {
    Sanctum::actingAs(editableTitleActor('create'));

    $response = $this->postJson('/api/opportunities', array_merge(nameDerivationOpportunityCreatePayload(), ['name' => null]))
        ->assertCreated();

    $opportunity = Opportunity::findOrFail($response->json('data.id'));
    expect($opportunity->name)->toBe('OPP_'.$opportunity->id)
        ->and($opportunity->name_is_manual)->toBeFalse()
        ->and($response->json('data.name_is_manual'))->toBeFalse();
});

it('AC-002: POST with a blank name stays automatic', function () {
    Sanctum::actingAs(editableTitleActor('create'));

    $response = $this->postJson('/api/opportunities', array_merge(nameDerivationOpportunityCreatePayload(), ['name' => '   ']))
        ->assertCreated();

    $opportunity = Opportunity::findOrFail($response->json('data.id'));
    expect($opportunity->name)->toBe('OPP_'.$opportunity->id)
        ->and($opportunity->name_is_manual)->toBeFalse();
});

it('AC-003: PATCH with a different name saves it as manual', function () {
    $opportunity = opportunityWithQuotedProduct();
    Sanctum::actingAs(editableTitleActor('update'));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['name' => 'Consulenza qualità'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Consulenza qualità')
        ->assertJsonPath('data.name_is_manual', true);
});

it('AC-004: PATCH with a null name goes back to the automatic title from the quotes', function () {
    $opportunity = opportunityWithQuotedProduct();
    $opportunity->forceFill(['name' => 'Typed title', 'name_is_manual' => true])->save();
    Sanctum::actingAs(editableTitleActor('update'));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['name' => null])
        ->assertOk()
        ->assertJsonPath('data.name', 'OPP_'.$opportunity->id.' - ISO 9001')
        ->assertJsonPath('data.name_is_manual', false);
});

it('AC-005: PATCH with the automatic title itself stays automatic', function () {
    $opportunity = opportunityWithQuotedProduct();
    Sanctum::actingAs(editableTitleActor('update'));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['name' => 'OPP_'.$opportunity->id.' - ISO 9001'])
        ->assertOk()
        ->assertJsonPath('data.name_is_manual', false);
});

it('AC-006: a manual title survives quote create, update and delete', function () {
    $opportunity = opportunityWithQuotedProduct();
    $opportunity->forceFill(['name' => 'Typed title', 'name_is_manual' => true])->save();
    $service = nameDerivationQuoteService();

    $quote = $service->create(
        nameDerivationCreateQuoteData($opportunity->id, [nameDerivationRevenueLine(nameDerivationRevenueProduct('SOA'))]),
        nameDerivationActor(),
    );
    expect($opportunity->fresh()->name)->toBe('Typed title');

    $service->delete(Quote::findOrFail($quote->id));
    expect($opportunity->fresh()->name)->toBe('Typed title')
        ->and($opportunity->fresh()->name_is_manual)->toBeTrue();
});

it('AC-006: an automatic title is still re-derived by a quote write', function () {
    $opportunity = opportunityWithQuotedProduct();

    nameDerivationQuoteService()->create(
        nameDerivationCreateQuoteData($opportunity->id, [nameDerivationRevenueLine(nameDerivationRevenueProduct('SOA'))]),
        nameDerivationActor(),
    );

    expect($opportunity->fresh()->name)->toBe('OPP_'.$opportunity->id.' - ISO 9001 + SOA');
});

it('AC-007: a name longer than 191 characters is refused with 422', function () {
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(editableTitleActor('update'));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['name' => Str::repeat('a', 192)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('PATCH name without opportunities.update is forbidden', function () {
    $opportunity = Opportunity::factory()->create(['name' => 'Original']);
    Sanctum::actingAs(editableTitleActor('view'));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['name' => 'Hijacked'])->assertForbidden();

    expect($opportunity->fresh()->name)->toBe('Original');
});
