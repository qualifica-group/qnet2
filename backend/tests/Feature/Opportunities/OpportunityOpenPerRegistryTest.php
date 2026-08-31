<?php

use App\Enums\CategoryManagementMode;
use App\Exceptions\Leads\BulkConversionBlockedException;
use App\Models\BusinessFunction;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\Opportunities\RegistryOpenOpportunityGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * User directive 2026-08-31: an anagrafica may carry ONE open opportunity at a
 * time — a second creation is refused, naming the opportunity that blocks it.
 *
 * "Open" is the COMPUTED status (spec 0082/0083): read off the opportunity's
 * quotes, with a quote-less opportunity displaying the global default set's
 * `open` row. Only the two terminal outcomes (`closed_won`/`closed_lost`)
 * free the anagrafica.
 *
 * Refinement of the same directive: an open opportunity managed on a SINGLE
 * product category (spec 0077) never blocks — it is a one-shot deal, not the
 * anagrafica's running business.
 */
uses(RefreshDatabase::class);

if (! function_exists('openPerRegistryActor')) {
    function openPerRegistryActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.viewAny', 'opportunities.view', 'opportunities.create', 'leads.view']);

        return $user;
    }
}

if (! function_exists('openPerRegistryPayload')) {
    /**
     * @return array<string, mixed>
     */
    function openPerRegistryPayload(Registry $registry): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => $registry->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

if (! function_exists('opportunityOnCategory')) {
    /** An opportunity of $registry whose only product line sits on a root category in $mode. */
    function opportunityOnCategory(Registry $registry, CategoryManagementMode $mode): Opportunity
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create([
            'business_function_id' => $businessFunction->id,
            'management_mode' => $mode,
        ]);

        $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);

        OpportunityProductLine::create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $businessFunction->id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

if (! function_exists('quoteInGroup')) {
    /** A quote of $opportunity sitting in one of the four system status rows. */
    function quoteInGroup(Opportunity $opportunity, string $systemKey): Quote
    {
        return Quote::factory()->create([
            'opportunity_id' => $opportunity->id,
            'quote_workflow_status_id' => QuoteWorkflowStatus::factory()->system($systemKey)->create()->id,
        ]);
    }
}

// ---------------------------------------------------------------------------
// A) The block itself, on POST /api/opportunities
// ---------------------------------------------------------------------------

it('creates the first opportunity of an anagrafica', function () {
    Sanctum::actingAs(openPerRegistryActor());

    $this->postJson('/api/opportunities', openPerRegistryPayload(Registry::factory()->create()))
        ->assertCreated();
});

it('refuses a second opportunity while a quote-less one is still open, naming it', function () {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    $open = Opportunity::factory()->create(['registry_id' => $registry->id]);

    $response = $this->postJson('/api/opportunities', openPerRegistryPayload($registry))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['registry_id']);

    expect($response->json('errors.registry_id.0'))->toContain($open->name);
    expect(Opportunity::query()->count())->toBe(1);
});

it('carries the blocking opportunity id so the client can link to it', function () {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    $open = Opportunity::factory()->create(['registry_id' => $registry->id]);

    $response = $this->postJson('/api/opportunities', openPerRegistryPayload($registry))->assertStatus(422);

    expect($response->json('errors.'.RegistryOpenOpportunityGuard::EXISTING_OPPORTUNITY_KEY.'.0'))
        ->toBe((string) $open->id);
});

it('never blocks on another anagrafica opportunity', function () {
    Sanctum::actingAs(openPerRegistryActor());
    Opportunity::factory()->create(['registry_id' => Registry::factory()->create()->id]);

    $this->postJson('/api/opportunities', openPerRegistryPayload(Registry::factory()->create()))
        ->assertCreated();
});

// ---------------------------------------------------------------------------
// B) What "open" means — the computed status, group by group
// ---------------------------------------------------------------------------

it('frees the anagrafica once every quote is closed', function (string $systemKey) {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    $previous = Opportunity::factory()->create(['registry_id' => $registry->id]);
    quoteInGroup($previous, $systemKey);

    $this->postJson('/api/opportunities', openPerRegistryPayload($registry))->assertCreated();
})->with(['closed_won', 'closed_lost']);

it('keeps blocking while a quote is still running', function (string $systemKey) {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    $previous = Opportunity::factory()->create(['registry_id' => $registry->id]);
    quoteInGroup($previous, $systemKey);

    $this->postJson('/api/opportunities', openPerRegistryPayload($registry))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['registry_id']);
})->with(['open', 'validated']);

it('blocks when only SOME of the quotes are closed', function () {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    $previous = Opportunity::factory()->create(['registry_id' => $registry->id]);
    quoteInGroup($previous, 'closed_won');
    quoteInGroup($previous, 'open');

    $this->postJson('/api/opportunities', openPerRegistryPayload($registry))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['registry_id']);
});

// ---------------------------------------------------------------------------
// C) The single-mode exemption
// ---------------------------------------------------------------------------

it('lets the anagrafica open another opportunity beside a single-category one', function () {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    opportunityOnCategory($registry, CategoryManagementMode::Single);

    $this->postJson('/api/opportunities', openPerRegistryPayload($registry))->assertCreated();
});

it('keeps blocking on an open opportunity managed on multiple categories', function () {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    opportunityOnCategory($registry, CategoryManagementMode::Multiple);

    $this->postJson('/api/opportunities', openPerRegistryPayload($registry))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['registry_id']);
});

it('blocks on the multiple-mode one even when a single-mode one sits beside it', function () {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    opportunityOnCategory($registry, CategoryManagementMode::Single);
    $blocking = opportunityOnCategory($registry, CategoryManagementMode::Multiple);

    $response = $this->postJson('/api/opportunities', openPerRegistryPayload($registry))->assertStatus(422);

    expect($response->json('errors.'.RegistryOpenOpportunityGuard::EXISTING_OPPORTUNITY_KEY.'.0'))
        ->toBe((string) $blocking->id);
});

it('converts a lead whose anagrafica only has a single-category opportunity', function () {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    opportunityOnCategory($registry, CategoryManagementMode::Single);
    $lead = Lead::factory()->create(['registry_id' => $registry->id, 'source_id' => Source::factory()]);

    $this->postJson('/api/leads/convert-to-opportunities', ['lead_ids' => [$lead->id]])->assertOk();

    expect(Opportunity::where('lead_id', $lead->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// D) The other creation paths (lead conversion, single and bulk)
// ---------------------------------------------------------------------------

it('refuses the single lead conversion when the lead anagrafica is busy', function () {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    Opportunity::factory()->create(['registry_id' => $registry->id]);
    $lead = Lead::factory()->create(['registry_id' => $registry->id, 'source_id' => Source::factory()]);

    $this->postJson('/api/leads/convert-to-opportunities', ['lead_ids' => [$lead->id]])
        ->assertStatus(422)
        ->assertJsonPath('errors.blockers.0.reason', BulkConversionBlockedException::BLOCKER_REGISTRY_HAS_OPEN_OPPORTUNITY);

    expect(Opportunity::where('lead_id', $lead->id)->count())->toBe(0);
});

it('refuses a batch holding two leads of the same anagrafica', function () {
    Sanctum::actingAs(openPerRegistryActor());
    $registry = Registry::factory()->create();
    $first = Lead::factory()->create(['registry_id' => $registry->id, 'source_id' => Source::factory()]);
    $second = Lead::factory()->create(['registry_id' => $registry->id, 'source_id' => Source::factory()]);

    $this->postJson('/api/leads/convert-to-opportunities', ['lead_ids' => [$first->id, $second->id]])
        ->assertStatus(422)
        ->assertJsonPath('errors.blockers.0.id', $second->id)
        ->assertJsonPath('errors.blockers.0.reason', BulkConversionBlockedException::BLOCKER_REGISTRY_HAS_OPEN_OPPORTUNITY);

    // D-1 is all-or-nothing: the convertible first lead is not converted either.
    expect(Opportunity::query()->count())->toBe(0);
});
