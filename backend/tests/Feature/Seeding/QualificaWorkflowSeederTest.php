<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The client's "stati di lavorazione" (spec 0047), transcribed from the
// "Stati di Lavorazione_Commerciale" sheet: one workflow per product category,
// seeded as the last step of QualificaCatalogSeeder.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

it('provisions one active workflow per catalogue category, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: natural key (name / signature), no duplicates.

    $expected = array_keys(WorkflowStatusCatalogue::WORKFLOWS);

    expect(QuoteWorkflow::query()->count())->toBe(count($expected));

    foreach ($expected as $categoryName) {
        $workflow = QuoteWorkflow::query()->where('name', $categoryName)->with('criteria')->first();
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

        expect($workflow)->not->toBeNull($categoryName)
            ->and($workflow->is_active)->toBeTrue($categoryName)
            // Matched on its own category alone.
            ->and($workflow->criteria)->toHaveCount(1, $categoryName)
            ->and($workflow->criteria->first()->field)->toBe('product_category_id', $categoryName)
            ->and($workflow->criteria->first()->value_id)->toBe($category->id, $categoryName);
    }
});

it('seeds a GOL region column in the sheet order, between the pinned system rows', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $workflow = QuoteWorkflow::query()->where('name', 'GOL - Lombardia')->firstOrFail();
    $statuses = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $workflow->id)
        ->orderBy('sort_order')
        ->get();

    // Every row of the Lombardia column is seeded once: three of them are
    // promoted onto pinned system rows, the rest stay custom. No extra row is
    // added — 'validated' is a group, not a system row (user directive
    // 2026-08-07), and the GOL block classifies no state under it.
    $all = WorkflowStatusCatalogue::statusesFor('GOL - Lombardia');
    $custom = WorkflowStatusCatalogue::customStatusesFor('GOL - Lombardia');

    expect($statuses)->toHaveCount(count($all))
        ->and(count($custom))->toBe(count($all) - 3)
        ->and($statuses->first()->system_key)->toBe('open')
        ->and($statuses->slice(-2)->pluck('system_key')->all())->toBe(['closed_won', 'closed_lost'])
        ->and($statuses->pluck('system_key')->filter()->values()->all())->not->toContain('validated');

    expect($statuses->slice(1, count($custom))->pluck('name')->values()->all())
        ->toBe(array_column($custom, 'name'));

    // First and last custom row of the column, as the sheet lists them —
    // 'Da Richiamare' is no longer here: it now labels the pinned open row.
    expect($statuses->get(1)->name)->toBe('Attesa esito SFL/ADI')
        ->and($statuses->get(count($custom))->name)->toBe('In Standby');
});

it('labels the pinned system rows with the sheet states, never the generic defaults', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $pinned = function (string $workflowName): array {
        $workflow = QuoteWorkflow::query()->where('name', $workflowName)->firstOrFail();

        return QuoteWorkflowStatus::query()
            ->where('quote_workflow_id', $workflow->id)
            ->whereNotNull('system_key')
            ->pluck('name', 'system_key')
            ->sortKeys()
            ->all();
    };

    // Each pinned row takes over the FIRST state its block classifies under
    // the same group. In AUTOIMPIEGO/YISU that is not "OK_Da Caricare": the
    // catalogue classifies it under the `validated` group, which is pinned to
    // nothing (user directive 2026-08-07), so closed_won falls to the next
    // green state.
    expect($pinned('GOL - Lombardia'))->toBe([
        'closed_lost' => 'Percorso 101',
        'closed_won' => 'Associato SI _ NOI',
        'open' => 'Da Richiamare',
    ]);

    expect($pinned('Consulenza'))->toBe([
        'closed_lost' => 'Persa',
        'closed_won' => 'VINTO',
        'open' => 'Da Richiamare',
    ]);

    expect($pinned('Autoimpiego'))->toBe([
        'closed_lost' => 'Non ha i Requisiti',
        'closed_won' => 'Associato SI _ NOI',
        'open' => 'Da Richiamare',
    ]);

    expect($pinned('Yisu'))->toBe([
        'closed_lost' => 'Non ha i Requisiti',
        'closed_won' => 'Associato SI _ NOI',
        'open' => 'Da Richiamare',
    ]);

    expect($pinned('Autofinanziato'))->toBe([
        'closed_lost' => 'Irreperibile',
        'closed_won' => 'OK_Iscritto',
        'open' => 'Da Richiamare',
    ]);
});

it('promotes a state onto a pinned row instead of duplicating it as a custom one', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $workflow = QuoteWorkflow::query()->where('name', 'Consulenza')->firstOrFail();
    $statuses = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $workflow->id)
        ->get();

    // One row per name, and the promoted ones carry their sheet description
    // and colour onto the system row.
    expect($statuses->pluck('name')->duplicates())->toBeEmpty();

    $vinto = $statuses->firstWhere('name', 'VINTO');

    expect($vinto->system_key)->toBe('closed_won')
        ->and($vinto->color)->toBe('green')
        ->and($vinto->description)->toBe('Trattativa conclusa positivamente.');
});

it('classifies each status from the sheet legend', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $workflow = QuoteWorkflow::query()->where('name', 'GOL - Lombardia')->firstOrFail();
    $statuses = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $workflow->id)
        ->get()
        ->keyBy('name');

    // No fill: "stato di lavorazione aperto".
    expect($statuses['In Standby']->group)->toBe(WorkflowStatusGroup::Open)
        ->and($statuses['In Standby']->color)->toBe('slate')
        // Yellow: open, but used only by the region it belongs to.
        ->and($statuses['Attesa Attivazione DOTE']->group)->toBe(WorkflowStatusGroup::Open)
        ->and($statuses['Attesa Attivazione DOTE']->color)->toBe('yellow')
        // Green: "Esito Positivo".
        ->and($statuses['Associato SI _ NOI']->group)->toBe(WorkflowStatusGroup::ClosedWon)
        ->and($statuses['Associato SI _ NOI']->color)->toBe('green')
        // Pink: "Esito Negativo".
        ->and($statuses['Irreperibile']->group)->toBe(WorkflowStatusGroup::ClosedLost)
        ->and($statuses['Irreperibile']->color)->toBe('red');

    // Never note-requiring: the sheet carries no such marker.
    expect($statuses->pluck('requires_note')->unique()->all())->toBe([false]);
});

it('scopes the descriptions per block, so one name reads differently per category', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $descriptionOf = function (string $workflowName, string $statusName): string {
        $workflow = QuoteWorkflow::query()->where('name', $workflowName)->firstOrFail();

        return QuoteWorkflowStatus::query()
            ->where('quote_workflow_id', $workflow->id)
            ->where('name', $statusName)
            ->value('description');
    };

    expect($descriptionOf('GOL - Molise', 'Da Richiamare'))
        ->toStartWith('Contatto da ricontattare per completare la lavorazione')
        ->and($descriptionOf('Autoimpiego', 'Da Richiamare'))
        ->toStartWith('Candidato da ricontattare per completare la lavorazione')
        ->and($descriptionOf('Consulenza', 'Da Richiamare'))
        ->toStartWith('Contatto da ricontattare per fornire informazioni');
});

it('gives the four regions sharing one column the same status list', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $names = static fn (string $workflowName): array => QuoteWorkflowStatus::query()
        ->whereIn('quote_workflow_id', QuoteWorkflow::query()->where('name', $workflowName)->select('id'))
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    $molise = $names('GOL - Molise');

    foreach (['GOL - Puglia', 'GOL - Calabria', 'GOL - Basilicata'] as $workflowName) {
        expect($names($workflowName))->toBe($molise, $workflowName);
    }

    // Autoimpiego and Yisu share the "uguale per tutte le regioni" block too.
    expect($names('Yisu'))->toBe($names('Autoimpiego'));
});

it('leaves the categories absent from the sheet on the global default set', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // No column in the sheet: no workflow, so their opportunities fall back to
    // the global default set (QuoteWorkflowResolver).
    foreach (['GOL - Abruzzo', 'DIL', 'Formazione', 'Trattative in Corso', 'Presa Appuntamenti'] as $categoryName) {
        expect(QuoteWorkflow::query()->where('name', $categoryName)->exists())->toBeFalse($categoryName);
    }
});
