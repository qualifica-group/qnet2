<?php

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Services\Quotes\QuoteWorkflowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// spec 0047 amendment 2026-07-27: resolver-level matching (AC-031) and
// N+1 guard (AC-032) for a custom relation criterion. Mirrors
// QuoteWorkflowResolverTest's own helpers (separate file: that one covers
// the base spec's native-field ACs, this one the amendment). Spec 0083,
// D-7: the criterion still matches on the OPPORTUNITY's custom_fields (the
// definitions stay anchored to the `opportunities` entity_type) — only the
// record being resolved is the Quote now, reached via `quote.opportunity`.
uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('workflowWithSystemStatuses')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function workflowWithSystemStatuses(array $attributes = []): QuoteWorkflow
    {
        $workflow = QuoteWorkflow::factory()->create($attributes);

        foreach (['open', 'closed_won', 'closed_lost'] as $key) {
            QuoteWorkflowStatus::factory()->system($key)->create(['quote_workflow_id' => $workflow->id]);
        }

        return $workflow;
    }
}

if (! function_exists('workflowResolver')) {
    function workflowResolver(): QuoteWorkflowResolver
    {
        return app(QuoteWorkflowResolver::class);
    }
}

if (! function_exists('companyRelationCustomField')) {
    // Signature (including the default) MUST stay identical to the
    // same-named helper in QuoteWorkflowCustomFieldCriteriaTest.php:
    // whichever file Pest loads first wins the definition (function_exists
    // guard), so a divergent signature there would break call sites here.
    function companyRelationCustomField(string $cardinality = 'one'): CustomFieldDefinition
    {
        return CustomFieldDefinition::factory()->forEntity('opportunities')->create([
            'key' => 'preferred_company',
            'type' => 'relation',
            'label' => 'Azienda preferita',
            'relation_target' => ['entity_type' => 'companies', 'cardinality' => $cardinality, 'for_select_resource' => 'companies'],
        ]);
    }
}

if (! function_exists('quoteForOpportunity')) {
    function quoteForOpportunity(Opportunity $opportunity): Quote
    {
        return Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    }
}

// ---------------------------------------------------------------------------
// AC-031 — cardinality one/many matching
// ---------------------------------------------------------------------------

it('matches a cardinality-one custom relation criterion on the exact value, not on a different one or null', function () {
    $definition = companyRelationCustomField('one');

    $matchingWorkflow = workflowWithSystemStatuses();
    $matchingWorkflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => 7]);

    $matchingOpportunity = Opportunity::factory()->create();
    CustomFieldValue::factory()->forEntity('opportunities', $matchingOpportunity->id)->create(['values' => ['preferred_company' => 7]]);
    $matchingQuote = quoteForOpportunity($matchingOpportunity);

    expect(workflowResolver()->resolve($matchingQuote)?->id)->toBe($matchingWorkflow->id);

    $differentValueOpportunity = Opportunity::factory()->create();
    CustomFieldValue::factory()->forEntity('opportunities', $differentValueOpportunity->id)->create(['values' => ['preferred_company' => 8]]);
    expect(workflowResolver()->resolve(quoteForOpportunity($differentValueOpportunity)))->toBeNull();

    $noValueOpportunity = Opportunity::factory()->create();
    expect(workflowResolver()->resolve(quoteForOpportunity($noValueOpportunity)))->toBeNull();
});

it('matches a cardinality-many custom relation criterion when the value is contained in the array (D7)', function () {
    $definition = companyRelationCustomField('many');

    $opportunity = Opportunity::factory()->create();
    CustomFieldValue::factory()->forEntity('opportunities', $opportunity->id)->create(['values' => ['preferred_company' => [3, 7, 9]]]);
    $quote = quoteForOpportunity($opportunity);

    $matchingWorkflow = workflowWithSystemStatuses();
    $matchingWorkflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => 7]);

    expect(workflowResolver()->resolve($quote)?->id)->toBe($matchingWorkflow->id);

    $nonMatchingWorkflow = workflowWithSystemStatuses();
    $nonMatchingWorkflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => 42]);
    $matchingWorkflow->update(['is_active' => false]);

    expect(workflowResolver()->resolve($quote))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-032 — no per-row query
// ---------------------------------------------------------------------------

it('resolves a multi-workflow candidate set with exactly ONE custom_field_values query, not one per workflow (AC-032)', function () {
    $definition = companyRelationCustomField('one');

    $opportunity = Opportunity::factory()->create();
    CustomFieldValue::factory()->forEntity('opportunities', $opportunity->id)->create(['values' => ['preferred_company' => 7]]);
    $quote = quoteForOpportunity($opportunity);

    foreach (range(1, 3) as $offset) {
        $nonMatching = workflowWithSystemStatuses();
        $nonMatching->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => 7 + $offset]);
    }

    $matching = workflowWithSystemStatuses();
    $matching->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => 7]);

    DB::enableQueryLog();
    $resolved = workflowResolver()->resolve($quote);
    $customFieldValueQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'custom_field_values'));
    DB::disableQueryLog();

    expect($resolved?->id)->toBe($matching->id)
        ->and($customFieldValueQueries)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// AC-034 — resolve() never throws for a criterion whose custom field
// definition is gone (D10); it simply never matches. isAllowed()'s
// short-circuit in matches() makes the exception structurally impossible
// today — these tests exist to keep it that way against a future change.
// ---------------------------------------------------------------------------

it('resolve() does not throw and does not match when the referenced custom field definition is DISABLED', function () {
    $definition = companyRelationCustomField('one');

    $opportunity = Opportunity::factory()->create();
    CustomFieldValue::factory()->forEntity('opportunities', $opportunity->id)->create(['values' => ['preferred_company' => 7]]);
    $quote = quoteForOpportunity($opportunity);

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => 7]);

    $definition->update(['is_active' => false]);

    expect(fn () => workflowResolver()->resolve($quote))->not->toThrow(Throwable::class);
    expect(workflowResolver()->resolve($quote))->toBeNull();
});

it('resolve() does not throw and does not match when the referenced custom field definition is DELETED', function () {
    $definition = companyRelationCustomField('one');

    $opportunity = Opportunity::factory()->create();
    CustomFieldValue::factory()->forEntity('opportunities', $opportunity->id)->create(['values' => ['preferred_company' => 7]]);
    $quote = quoteForOpportunity($opportunity);

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => 7]);

    $definition->delete();

    expect(fn () => workflowResolver()->resolve($quote))->not->toThrow(Throwable::class);
    expect(workflowResolver()->resolve($quote))->toBeNull();
});
