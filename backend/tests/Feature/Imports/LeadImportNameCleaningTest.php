<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Imports\LeadsImportDefinition;
use App\Imports\Staging\StagedRowBuilder;
use App\Imports\Staging\StageOutcome;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Spec 0138 AC-004 — a lead row staged through the real LeadsImportDefinition
 * recognizer chain: names are cleaned before the full_name split.
 */
function stageNameCleaningRow(string $fullName): StageOutcome
{
    return (new StagedRowBuilder(
        app(LeadsImportDefinition::class),
        User::factory()->create(),
        ['Nome completo' => 'full_name', 'Email' => 'email'],
        ImportDedupMode::CreateNew,
    ))->build(1, ['Nome completo' => $fullName, 'Email' => 'mery.rossi@example.com']);
}

it('AC-004: splits the cleaned full_name and flags the row as a warning', function () {
    $outcome = stageNameCleaningRow("\u{1D4DC}\u{1D4EE}\u{1D4FB}\u{1D502} \u{1D4E1}\u{1D4F8}\u{1D4FC}\u{1D4FC}\u{1D4F2}");

    expect($outcome->mappedValues['first_name'])->toBe('Mery')
        ->and($outcome->mappedValues['last_name'])->toBe('Rossi')
        ->and($outcome->mappedValues['full_name'])->toBe('Mery Rossi')
        ->and($outcome->status)->toBe(ImportRowStatus::Warning)
        ->and($outcome->messages)->toContain('full_name contained special characters and was cleaned to "Mery Rossi"; review it.');
});

it('AC-005: a clean upper-case name is stored with the card-form casing, as a valid row', function () {
    $outcome = stageNameCleaningRow('MARIA TERESA ROSSI');

    expect($outcome->mappedValues['first_name'])->toBe('Maria Teresa')
        ->and($outcome->mappedValues['last_name'])->toBe('Rossi')
        ->and($outcome->status)->toBe(ImportRowStatus::Valid);
});

it('AC-004: a name made only of symbols falls back to the placeholder', function () {
    $outcome = stageNameCleaningRow("\u{2661}");

    expect($outcome->mappedValues['first_name'])->toBe(config('imports.placeholder'))
        ->and($outcome->status)->toBe(ImportRowStatus::Warning);
});
