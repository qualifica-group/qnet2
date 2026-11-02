<?php

use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0331 (MT-02): the read-only offer data the commessa detail projects off
 * its single `quote`, and null / [] on a multi-offer commessa, with
 * preventLazyLoading active (AC-005).
 */
uses(RefreshDatabase::class);

it('GET projects referent, commercial, reporter, source, lines, rewards, layout and payment method from the single offer', function () {
    $quote = offerProjectionFullQuote();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs(workOrderUserWith(['view']));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.referent.id', $quote->opportunity->referent_id)
        ->assertJsonPath('data.referent.referent_type.name', $quote->opportunity->referent->referentType->name)
        ->assertJsonPath('data.commercial.id', $quote->commercial_id)
        ->assertJsonPath('data.reporter.id', $quote->reporter_id)
        ->assertJsonPath('data.source.id', $quote->source_id)
        ->assertJsonCount(1, 'data.product_lines')
        ->assertJsonPath('data.rewards.0.id', $quote->rewards->first()->id)
        ->assertJsonPath('data.layout.id', $quote->layout_id)
        ->assertJsonPath('data.payment_method.id', $quote->payment_method_id);
});

it('GET projects null / [] on a multi-offer commessa', function () {
    [$workOrder] = multiQuoteReadFixture();
    Sanctum::actingAs(workOrderUserWith(['view']));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.is_multi_quote', true)
        ->assertJsonPath('data.referent', null)
        ->assertJsonPath('data.commercial', null)
        ->assertJsonPath('data.reporter', null)
        ->assertJsonPath('data.source', null)
        ->assertJsonPath('data.layout', null)
        ->assertJsonPath('data.payment_method', null)
        ->assertJsonPath('data.product_lines', [])
        ->assertJsonPath('data.rewards', []);
});
