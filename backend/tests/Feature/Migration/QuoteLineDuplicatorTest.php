<?php

use App\Migrations\Support\QuoteLineDuplicator;
use App\Models\QuoteLine;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('AC-009: the duplicated line keeps the supplier of the source line and drops its old_id', function () {
    $supplier = Registry::factory()->supplier()->create();
    $line = QuoteLine::factory()->create(['supplier_id' => $supplier->id, 'old_id' => 900]);

    $copy = app(QuoteLineDuplicator::class)->duplicate($line->load('quote'));

    expect($copy->id)->not->toBe($line->id)
        ->and($copy->supplier_id)->toBe($supplier->id)
        ->and($copy->old_id)->toBeNull();
});
