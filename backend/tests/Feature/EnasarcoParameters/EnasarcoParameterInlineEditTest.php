<?php

declare(strict_types=1);

use App\Models\EnasarcoParameter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Spec 0264: partial PATCH (D-1) and cell editing (D-3) of the ENASARCO parameters.

uses(RefreshDatabase::class);

function patchParameterCell(int $id, string $column, mixed $value): TestResponse
{
    return test()->patchJson("/api/tables/enasarco-parameters/rows/{$id}", ['column' => $column, 'value' => $value]);
}

it('AC-002: a PATCH with only a ceiling leaves the rest untouched', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['update']));
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);

    $this->patchJson("/api/enasarco-parameters/{$parameter->id}", ['multi_firm_ceiling' => '30000.00'])
        ->assertOk()
        ->assertJsonPath('data.multi_firm_ceiling', '30000.00');

    $fresh = $parameter->fresh();
    expect($fresh->year)->toBe(2026)
        ->and($fresh->total_rate)->toBe('17.00')
        ->and($fresh->agent_share_percent)->toBe('50.00')
        ->and($fresh->single_firm_ceiling)->toBe('45000.00');
});

it('AC-002: a PATCH with a duplicate year is refused', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['update']));
    EnasarcoParameter::factory()->create(['year' => 2025]);
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);

    $this->patchJson("/api/enasarco-parameters/{$parameter->id}", ['year' => 2025])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['year']);
});

it('AC-004: declares the editable columns', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny', 'update']));

    $columns = collect($this->getJson('/api/tables/enasarco-parameters/columns')->assertOk()->json('data.columns'));

    expect($columns->filter(fn (array $column): bool => ($column['editable'] ?? false) === true)->pluck('id')->sort()->values()->all())->toBe([
        'agent_share_percent', 'multi_firm_ceiling', 'single_firm_ceiling', 'total_rate', 'year',
    ]);
});

it('AC-004: saves the year and the ceilings of a cell, a duplicate year is refused', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny', 'update']));
    EnasarcoParameter::factory()->create(['year' => 2025]);
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);

    patchParameterCell($parameter->id, 'year', 2027)->assertOk()->assertJsonPath('data.year', 2027);
    patchParameterCell($parameter->id, 'single_firm_ceiling', 50000)->assertOk();
    patchParameterCell($parameter->id, 'multi_firm_ceiling', 31000.5)->assertOk();
    patchParameterCell($parameter->id, 'year', 2025)->assertUnprocessable()->assertJsonValidationErrors(['year']);
    patchParameterCell($parameter->id, 'total_rate', 101)->assertUnprocessable();

    $fresh = $parameter->fresh();
    expect($fresh->year)->toBe(2027)
        ->and($fresh->single_firm_ceiling)->toBe('50000.00')
        ->and($fresh->multi_firm_ceiling)->toBe('31000.50')
        ->and($fresh->total_rate)->toBe('17.00');
});

it('AC-004: nothing is editable without update (403)', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny']));
    $parameter = EnasarcoParameter::factory()->create();

    patchParameterCell($parameter->id, 'total_rate', 18)->assertForbidden();
});
