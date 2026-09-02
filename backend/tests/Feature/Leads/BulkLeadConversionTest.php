<?php

use App\Exceptions\Leads\BulkConversionBlockedException;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/** The mass action under test (spec 0071). */
const BULK_CONVERT_URI = '/api/leads/convert-to-opportunities';

if (! function_exists('bulkConversionActor')) {
    /**
     * @param  array<int, string>  $leadAbilities
     * @param  array<int, string>  $opportunityAbilities
     */
    function bulkConversionActor(array $leadAbilities, array $opportunityAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($leadAbilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        foreach ($opportunityAbilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('convertibleLead')) {
    /**
     * A lead the batch conversion accepts: the default CampaignFactory
     * campaign already carries a coherent business function / product
     * category pair, which is exactly what the derivation needs.
     */
    function convertibleLead(): Lead
    {
        return Lead::factory()->create(['source_id' => Source::factory()]);
    }
}

if (! function_exists('nonDerivableLead')) {
    /**
     * A lead whose campaign derives no product line (legacy data). Spec
     * 0094: `campaigns.product_category_id` no longer exists — the coherent
     * row CampaignFactory auto-creates for a standalone campaign is dropped
     * here instead, leaving the campaign with none. Requirement changed by
     * spec 0094, not test tampering.
     */
    function nonDerivableLead(): Lead
    {
        $campaign = Campaign::factory()->create();
        $campaign->productLines()->delete();

        return Lead::factory()->create(['campaign_id' => $campaign->id]);
    }
}

// ---------------------------------------------------------------------------
// A) Happy path (AC-001..AC-004)
// ---------------------------------------------------------------------------

it('AC-001: converts every selected lead and reports how many', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $leads = collect([convertibleLead(), convertibleLead(), convertibleLead()]);

    $response = $this->postJson(BULK_CONVERT_URI, ['lead_ids' => $leads->pluck('id')->all()])->assertOk();

    expect($response->json('data.converted'))->toBe(3);
    foreach ($leads as $lead) {
        expect(Opportunity::where('lead_id', $lead->id)->count())->toBe(1);
    }
});

it('AC-002: returns the ids of the created Opportunities', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $leads = collect([convertibleLead(), convertibleLead()]);

    $response = $this->postJson(BULK_CONVERT_URI, ['lead_ids' => $leads->pluck('id')->all()])->assertOk();

    expect($response->json('data.opportunity_ids'))
        ->toEqualCanonicalizing(Opportunity::whereIn('lead_id', $leads->pluck('id'))->pluck('id')->all());
});

it('AC-003: derives each Opportunity exactly like the contextual conversion does', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $lead = convertibleLead();
    // Spec 0094: the campaign's classification is a `productLines`
    // collection now, not two single columns — read the persisted row
    // instead. Requirement changed by spec 0094, not test tampering.
    $campaignLine = $lead->campaign->productLines()->firstOrFail();

    $this->postJson(BULK_CONVERT_URI, ['lead_ids' => [$lead->id]])->assertOk();

    $opportunity = Opportunity::where('lead_id', $lead->id)->firstOrFail();
    $opportunity->load('productLines');

    expect($opportunity->registry_id)->toBe($lead->registry_id);
    expect($opportunity->source_id)->toBe($lead->source_id);
    expect($opportunity->productLines)->toHaveCount(1);
    expect($opportunity->productLines->first()->business_function_id)->toBe($campaignLine->business_function_id);
    expect($opportunity->productLines->first()->product_category_id)->toBe($campaignLine->product_category_id);
});

it('AC-004: converts a duplicated id only once', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $first = convertibleLead();
    $second = convertibleLead();

    $response = $this->postJson(BULK_CONVERT_URI, [
        'lead_ids' => [$first->id, $first->id, $second->id],
    ])->assertOk();

    expect($response->json('data.converted'))->toBe(2);
    expect(Opportunity::where('lead_id', $first->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// B) All-or-nothing (AC-010..AC-014)
// ---------------------------------------------------------------------------

it('AC-010: rejects the batch when one lead is already converted', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $converted = convertibleLead();
    Opportunity::factory()->create(['lead_id' => $converted->id]);

    $response = $this->postJson(BULK_CONVERT_URI, [
        'lead_ids' => [convertibleLead()->id, $converted->id],
    ])->assertStatus(422);

    expect($response->json('errors.reason'))->toBe(BulkConversionBlockedException::REASON);
    expect($response->json('errors.blockers'))->toBe([
        ['id' => $converted->id, 'reason' => BulkConversionBlockedException::BLOCKER_ALREADY_CONVERTED],
    ]);
});

it('AC-011: a rejected batch converts none of the other leads', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $converted = convertibleLead();
    Opportunity::factory()->create(['lead_id' => $converted->id]);
    $others = collect([convertibleLead(), convertibleLead()]);
    $before = Opportunity::count();

    $this->postJson(BULK_CONVERT_URI, [
        'lead_ids' => $others->pluck('id')->push($converted->id)->all(),
    ])->assertStatus(422);

    expect(Opportunity::count())->toBe($before);
    expect(Opportunity::whereIn('lead_id', $others->pluck('id'))->count())->toBe(0);
});

it('AC-012: rejects the batch when a lead campaign derives no product line', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $blocked = nonDerivableLead();

    $response = $this->postJson(BULK_CONVERT_URI, [
        'lead_ids' => [convertibleLead()->id, $blocked->id],
    ])->assertStatus(422);

    expect($response->json('errors.blockers'))->toBe([
        ['id' => $blocked->id, 'reason' => BulkConversionBlockedException::BLOCKER_NOT_DERIVABLE],
    ]);
    expect(Opportunity::count())->toBe(0);
});

it('AC-013: lists every offending lead, not just the first', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $converted = convertibleLead();
    Opportunity::factory()->create(['lead_id' => $converted->id]);
    $notDerivable = nonDerivableLead();

    $response = $this->postJson(BULK_CONVERT_URI, [
        'lead_ids' => [$converted->id, $notDerivable->id, convertibleLead()->id],
    ])->assertStatus(422);

    expect($response->json('errors.blockers'))->toEqualCanonicalizing([
        ['id' => $converted->id, 'reason' => BulkConversionBlockedException::BLOCKER_ALREADY_CONVERTED],
        ['id' => $notDerivable->id, 'reason' => BulkConversionBlockedException::BLOCKER_NOT_DERIVABLE],
    ]);
});

it('AC-014: rolls back the whole batch when a conversion fails mid-way', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $leads = collect([convertibleLead(), convertibleLead(), convertibleLead()]);

    // Fails the SECOND insert only: the first Opportunity is already created
    // when it happens, so surviving rows would prove a missing transaction.
    $attempts = 0;
    Opportunity::creating(function () use (&$attempts): void {
        $attempts++;

        if ($attempts === 2) {
            throw new RuntimeException('conversion failed mid-batch');
        }
    });

    $this->postJson(BULK_CONVERT_URI, ['lead_ids' => $leads->pluck('id')->all()])->assertStatus(500);

    expect(Opportunity::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// C) Authorization and batch limits (AC-020..AC-024)
// ---------------------------------------------------------------------------

it('AC-020: forbids an actor without opportunities.create', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], []));

    $this->postJson(BULK_CONVERT_URI, ['lead_ids' => [convertibleLead()->id]])->assertForbidden();

    expect(Opportunity::count())->toBe(0);
});

it('AC-021: forbids an actor who cannot view the targeted leads', function () {
    Sanctum::actingAs(bulkConversionActor([], ['create']));

    $this->postJson(BULK_CONVERT_URI, ['lead_ids' => [convertibleLead()->id]])->assertForbidden();

    expect(Opportunity::count())->toBe(0);
});

it('AC-022: rejects a batch larger than the configured cap', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    config(['leads.bulk_conversion_max' => 2]);
    $leads = collect([convertibleLead(), convertibleLead(), convertibleLead()]);

    $this->postJson(BULK_CONVERT_URI, ['lead_ids' => $leads->pluck('id')->all()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('lead_ids');

    expect(Opportunity::count())->toBe(0);
});

it('AC-023: rejects an empty selection', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));

    $this->postJson(BULK_CONVERT_URI, ['lead_ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('lead_ids');
});

it('AC-024: rejects an unknown lead id', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));

    $this->postJson(BULK_CONVERT_URI, ['lead_ids' => [999999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('lead_ids.0');
});

// ---------------------------------------------------------------------------
// E) The generated Offerta reuses the single action (spec 0094, AC-068/AC-069)
// ---------------------------------------------------------------------------

if (! function_exists('convertibleLeadWithInterest')) {
    /** A convertibleLead() carrying one product of interest in its campaign's own category. */
    function convertibleLeadWithInterest(): Lead
    {
        $lead = convertibleLead();
        $categoryId = $lead->campaign->productLines()->value('product_category_id');
        $product = Product::factory()->create(['category_id' => $categoryId]);
        $lead->productsOfInterest()->attach($product->id);

        return $lead;
    }
}

it('AC-068: converts every selected lead with the same Offerta-generation effects as the single action', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $leads = collect([convertibleLeadWithInterest(), convertibleLeadWithInterest()]);

    $this->postJson(BULK_CONVERT_URI, ['lead_ids' => $leads->pluck('id')->all()])->assertOk();

    foreach ($leads as $lead) {
        $opportunity = Opportunity::where('lead_id', $lead->id)->firstOrFail();
        $quote = Quote::where('opportunity_id', $opportunity->id)->firstOrFail();
        expect($quote->offerLines)->toHaveCount(1);
    }
});

it('AC-069: a lead already converted (blocked up front) does not generate a second Offerta', function () {
    Sanctum::actingAs(bulkConversionActor(['view'], ['create']));
    $converted = convertibleLeadWithInterest();
    Opportunity::factory()->create(['lead_id' => $converted->id]);
    $quoteCountBefore = Quote::count();

    $this->postJson(BULK_CONVERT_URI, ['lead_ids' => [$converted->id]])
        ->assertStatus(422)
        ->assertJsonPath('errors.blockers.0.reason', BulkConversionBlockedException::BLOCKER_ALREADY_CONVERTED);

    expect(Quote::count())->toBe($quoteCountBefore);
});
