<?php

use App\Http\Resources\ProductForSelectResource;
use App\Models\Product;
use App\Models\QuoteLine;
use App\Models\VatRate;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// `costs:clear-vat` — one-off: every offer COST line and work order cost goes
// to the 0% cost VAT rate (VAT 0, total = net). Offer REVENUE lines keep theirs.

beforeEach(function () {
    $this->zero = VatRate::factory()->create(['name' => 'IVA al 0%', 'rate' => 0]);
    $this->rate = VatRate::factory()->create(['rate' => 22]);
    $taxed = ['vat_rate_id' => $this->rate->id, 'net_amount' => 100, 'vat_amount' => 22, 'total_amount' => 122];
    $this->costLine = QuoteLine::factory()->cost()->create($taxed);
    $this->revenueLine = QuoteLine::factory()->create($taxed);
    $this->workOrderCost = WorkOrderCost::factory()->create($taxed);
    $this->emptyCost = WorkOrderCost::factory()->create(['vat_rate_id' => null, 'net_amount' => 50, 'vat_amount' => 0, 'total_amount' => 50]);
});

it('moves offer cost lines and work order costs onto the 0% rate', function () {
    $this->artisan('costs:clear-vat')
        ->expectsOutput('1 offer cost line(s) set to IVA al 0%.')
        ->expectsOutput('2 work order cost(s) set to IVA al 0%.')
        ->assertSuccessful();

    foreach ([$this->costLine->fresh(), $this->workOrderCost->fresh()] as $cost) {
        expect($cost->vat_rate_id)->toBe($this->zero->id)
            ->and($cost->vat_amount)->toBe('0.00')
            ->and($cost->total_amount)->toBe('100.00');
    }
    expect($this->emptyCost->fresh()->vat_rate_id)->toBe($this->zero->id);
});

it('leaves the offer revenue lines untouched', function () {
    $this->artisan('costs:clear-vat')->assertSuccessful();

    expect($this->revenueLine->fresh())
        ->vat_rate_id->toBe($this->rate->id)
        ->vat_amount->toBe('22.00')
        ->total_amount->toBe('122.00');
});

it('writes nothing on a dry run', function () {
    $this->artisan('costs:clear-vat --dry-run')
        ->expectsOutput('1 offer cost line(s) would be set to IVA al 0%.')
        ->expectsOutput('2 work order cost(s) would be set to IVA al 0%.')
        ->assertSuccessful();

    expect($this->costLine->fresh()->vat_rate_id)->toBe($this->rate->id)
        ->and($this->workOrderCost->fresh()->vat_rate_id)->toBe($this->rate->id);
});

it('touches nothing on a second run', function () {
    $this->artisan('costs:clear-vat')->assertSuccessful();

    $this->artisan('costs:clear-vat')
        ->expectsOutput('0 offer cost line(s) set to IVA al 0%.')
        ->expectsOutput('0 work order cost(s) set to IVA al 0%.')
        ->assertSuccessful();
});

it('fails without writing when the 0% rate does not exist', function () {
    $this->zero->delete();

    $this->artisan('costs:clear-vat')->assertFailed();

    expect($this->costLine->fresh()->vat_rate_id)->toBe($this->rate->id);
});

it('offers the 0% rate to cost rows through the product picker', function () {
    $product = Product::factory()->create(['vat_rate_id' => $this->rate->id]);

    $meta = (new ProductForSelectResource($product->load('vatRate')))->resolve(request())['meta'];

    expect($meta['vat_rate_id'])->toBe($this->rate->id)
        ->and($meta['cost_vat_rate'])->toBe(['id' => $this->zero->id, 'name' => 'IVA al 0%', 'rate' => '0.00']);
});
