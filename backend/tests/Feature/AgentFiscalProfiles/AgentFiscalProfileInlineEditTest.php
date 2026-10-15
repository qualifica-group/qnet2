<?php

declare(strict_types=1);

use App\Models\AgentFiscalProfile;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Spec 0264: partial PATCH (D-1) and cell editing (D-2) of the agent fiscal profiles.

uses(RefreshDatabase::class);

function patchProfileCell(int $id, string $column, mixed $value): TestResponse
{
    return test()->patchJson("/api/tables/agent-fiscal-profiles/rows/{$id}", ['column' => $column, 'value' => $value]);
}

it('AC-001: a PATCH with only the notes leaves the rest untouched', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['update']));
    $profile = AgentFiscalProfile::factory()->withEnasarco('mono')->create(['valid_until' => '2026-12-31']);

    $this->patchJson("/api/agent-fiscal-profiles/{$profile->id}", ['notes' => 'Solo note'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Solo note');

    $fresh = $profile->fresh();
    expect($fresh->withholding_rate)->toBe('23.00')
        ->and($fresh->withholding_base_percent)->toBe('50.00')
        ->and($fresh->enasarco_mandate_type->value)->toBe('mono')
        ->and($fresh->valid_from->toDateString())->toBe('2026-01-01')
        ->and($fresh->valid_until->toDateString())->toBe('2026-12-31');
});

it('AC-001: a PATCH switching the withholding off without clearing the rates is refused', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['update']));
    $profile = AgentFiscalProfile::factory()->create();

    $this->patchJson("/api/agent-fiscal-profiles/{$profile->id}", ['withholding_enabled' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['withholding_rate', 'withholding_base_percent']);

    $this->patchJson("/api/agent-fiscal-profiles/{$profile->id}", ['withholding_enabled' => false, 'withholding_rate' => null, 'withholding_base_percent' => null])
        ->assertOk();
});

it('AC-001: a PATCH on a profile with the withholding off and saved rates null keeps passing', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['update']));
    $profile = AgentFiscalProfile::factory()->withoutWithholding()->create();

    $this->patchJson("/api/agent-fiscal-profiles/{$profile->id}", ['notes' => 'Ok', 'withholding_enabled' => false])->assertOk();
});

it('AC-001: a PATCH overlapping another validity range is refused', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['update']));
    $first = AgentFiscalProfile::factory()->create(['valid_from' => '2025-01-01', 'valid_until' => '2025-12-31']);
    $second = AgentFiscalProfile::factory()->create(['referent_id' => $first->referent_id, 'valid_from' => '2026-01-01']);

    $this->patchJson("/api/agent-fiscal-profiles/{$second->id}", ['valid_from' => '2025-06-01'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['valid_from']);
});

it('AC-003: declares the editable columns with their cell shape', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny', 'update']));

    $columns = collect($this->getJson('/api/tables/agent-fiscal-profiles/columns')->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns->filter(fn (array $column): bool => ($column['editable'] ?? false) === true)->keys()->sort()->values()->all())->toBe([
        'enasarco_mandate_type', 'valid_from', 'valid_until', 'vat_rate', 'withholding_base_percent', 'withholding_rate',
    ])
        ->and($columns['valid_until'])->toMatchArray(['type' => 'date'])
        ->and($columns['enasarco_mandate_type']['options'])->toBe(['mono', 'pluri'])
        ->and($columns['vat_rate'])->toMatchArray(['editor' => 'relation', 'relation' => ['resource' => 'vat-rates']]);
});

it('AC-003: saves the validity end, the rate and the VAT rate of a cell', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny', 'update']));
    $profile = AgentFiscalProfile::factory()->create();
    $vat = VatRate::factory()->create(['name' => 'Ordinaria', 'rate' => '22.00']);

    patchProfileCell($profile->id, 'valid_until', '2026-06-30')->assertOk()->assertJsonPath('data.valid_until', '2026-06-30');
    patchProfileCell($profile->id, 'withholding_rate', 20)->assertOk()->assertJsonPath('data.withholding_rate', 20);
    patchProfileCell($profile->id, 'vat_rate', $vat->id)->assertOk()->assertJsonPath('data.vat_rate_id', $vat->id);
    patchProfileCell($profile->id, 'vat_rate', null)->assertOk()->assertJsonPath('data.vat_rate_id', null);
    patchProfileCell($profile->id, 'valid_until', null)->assertOk()->assertJsonPath('data.valid_until', null);

    expect($profile->fresh()->withholding_rate)->toBe('20.00')
        ->and($profile->fresh()->withholding_base_percent)->toBe('50.00');
});

it('AC-003: a cell edit runs the form rules (percentage range, overlap, mandate type)', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny', 'update']));
    $first = AgentFiscalProfile::factory()->create(['valid_from' => '2025-01-01', 'valid_until' => '2025-12-31']);
    $second = AgentFiscalProfile::factory()->withEnasarco('mono')->create(['referent_id' => $first->referent_id, 'valid_from' => '2026-01-01']);

    patchProfileCell($second->id, 'withholding_rate', 150)->assertUnprocessable();
    patchProfileCell($second->id, 'valid_from', '2025-06-01')->assertUnprocessable()->assertJsonValidationErrors(['valid_from']);
    patchProfileCell($second->id, 'enasarco_mandate_type', 'pluri')->assertOk()->assertJsonPath('data.enasarco_mandate_type', 'pluri');
    patchProfileCell($second->id, 'enasarco_mandate_type', 'bogus')->assertUnprocessable();
});

it('AC-003: the rate of a row with the withholding off is not editable', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny', 'update']));
    $profile = AgentFiscalProfile::factory()->withoutWithholding()->create();

    $rows = $this->postJson('/api/tables/agent-fiscal-profiles/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');
    expect($rows[0]['editable_columns'])->toEqualCanonicalizing(['valid_from', 'valid_until', 'vat_rate']);

    patchProfileCell($profile->id, 'withholding_rate', 10)->assertUnprocessable()->assertJsonValidationErrors(['withholding_rate']);
    expect($profile->fresh()->withholding_rate)->toBeNull();
});

it('AC-003: nothing is editable without update (403)', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny']));
    $profile = AgentFiscalProfile::factory()->create();

    patchProfileCell($profile->id, 'valid_until', '2026-06-30')->assertForbidden();
});
