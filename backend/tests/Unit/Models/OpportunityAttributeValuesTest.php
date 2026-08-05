<?php

use App\Models\Opportunity;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Request Management module (spec 0049, D-4/AC-001): opportunities.attribute_values
// is a JSON nullable column, cast to array, deliberately NOT mass-assignable
// (written exclusively by RequestManagementService).

uses(TestCase::class, RefreshDatabase::class);

it('adds the attribute_values json nullable column to opportunities', function () {
    expect(Schema::hasColumn('opportunities', 'attribute_values'))->toBeTrue();

    $opportunity = Opportunity::factory()->create();

    expect($opportunity->attribute_values)->toBeNull();
});

it('casts attribute_values to array on the model', function () {
    $opportunity = Opportunity::factory()->create();

    $opportunity->forceFill(['attribute_values' => ['warehouse_size' => 120, 'has_forklift' => true]])->save();
    $opportunity->refresh();

    expect($opportunity->attribute_values)->toBeArray()
        ->and($opportunity->attribute_values)->toBe(['warehouse_size' => 120, 'has_forklift' => true]);
});

it('attribute_values is NOT mass-assignable (absent from Fillable)', function () {
    // Opportunity::factory()->create() would NOT prove this: Factory building
    // runs inside Model::unguarded(), bypassing mass-assignment guarding by
    // design. Use the real Model::create()/fill() path instead.
    $registry = Registry::factory()->create();

    $opportunity = Opportunity::create([
        'name' => 'Test deal',
        'registry_id' => $registry->id,
        'attribute_values' => ['warehouse_size' => 120],
    ]);

    expect($opportunity->attribute_values)->toBeNull();

    $opportunity->fill(['attribute_values' => ['warehouse_size' => 999]]);

    expect($opportunity->attribute_values)->toBeNull();
});
