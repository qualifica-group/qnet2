<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\QuoteWorkflow;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Every negative outcome of the "Consulenza" list demands a note, except the
// two that only record an unreachable contact (user directive 2026-10-08).
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

it('marks every Consulenza negative state as note-requiring except the unreachable ones', function (): void {
    $statuses = collect(WorkflowStatusCatalogue::statusesFor('Consulenza'))->keyBy('name');

    $noteRequiring = $statuses->where('requires_note', true)->keys()->sort()->values()->all();

    expect($noteRequiring)->toBe(['Annullata', 'Non pertinente', 'Persa'])
        ->and($statuses['Irreperibile']['requires_note'])->toBeFalse()
        ->and($statuses['Numero inesistente']['requires_note'])->toBeFalse()
        ->and($statuses->where('group', '!=', WorkflowStatusGroup::ClosedLost->value)->pluck('requires_note')->unique()->all())
        ->toBe([false]);
});

it('persists the note requirement on the seeded Consulenza set, its pinned loss included', function (): void {
    $this->seed(QualificaCatalogSeeder::class);

    $statuses = QuoteWorkflow::query()->where('name', 'Consulenza')->firstOrFail()
        ->statuses()->get()->keyBy('name');

    // "Persa" is promoted onto the pinned closed_lost system row.
    expect($statuses['Persa']->system_key)->not->toBeNull()
        ->and($statuses->where('requires_note', true)->keys()->sort()->values()->all())
        ->toBe(['Annullata', 'Non pertinente', 'Persa']);
});

it('leaves every other catalogue list without note-requiring states', function (): void {
    $otherCategories = array_diff(array_keys(WorkflowStatusCatalogue::WORKFLOWS), ['Consulenza']);

    foreach ($otherCategories as $categoryName) {
        expect(array_unique(array_column(WorkflowStatusCatalogue::statusesFor($categoryName), 'requires_note')))
            ->toBe([false], $categoryName);
    }
});
