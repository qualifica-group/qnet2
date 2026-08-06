<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Spec 0084 (D-1/D-5): `quotes.attribute_values` is a JSON nullable column,
// cast to array, deliberately NOT mass-assignable (written exclusively by
// QuoteAttributeValueWriter) — moved here from the former
// `opportunities.attribute_values` (spec 0049 D-4).

uses(TestCase::class, RefreshDatabase::class);

it('adds the attribute_values json nullable column to quotes', function () {
    expect(Schema::hasColumn('quotes', 'attribute_values'))->toBeTrue();

    $quote = Quote::factory()->create();

    expect($quote->attribute_values)->toBeNull();
});

it('casts attribute_values to array on the model', function () {
    $quote = Quote::factory()->create();

    $quote->forceFill(['attribute_values' => ['warehouse_size' => 120, 'has_forklift' => true]])->save();
    $quote->refresh();

    expect($quote->attribute_values)->toBeArray()
        ->and($quote->attribute_values)->toBe(['warehouse_size' => 120, 'has_forklift' => true]);
});

it('attribute_values is NOT mass-assignable (absent from Fillable)', function () {
    // Factory::create() would NOT prove this: Factory building runs inside
    // Model::unguarded(), bypassing mass-assignment guarding by design. Build
    // the real Model::fill() path instead, assigning the two service-only
    // columns (`code`/`quote_workflow_status_id`) directly like QuoteService
    // does, since neither is fillable either.
    $opportunity = Opportunity::factory()->create();
    $openStatusId = QuoteWorkflowStatus::query()
        ->whereNull('quote_workflow_id')
        ->where('system_key', 'open')
        ->value('id')
        ?? QuoteWorkflowStatus::factory()->global()->system('open')->create()->id;

    $quote = new Quote([
        'title' => 'Test quote',
        'opportunity_id' => $opportunity->id,
        'attribute_values' => ['warehouse_size' => 120],
    ]);
    $quote->code = 'QUO-9999';
    $quote->quote_workflow_status_id = $openStatusId;
    $quote->save();

    expect($quote->attribute_values)->toBeNull();

    $quote->fill(['attribute_values' => ['warehouse_size' => 999]]);

    expect($quote->attribute_values)->toBeNull();
});
