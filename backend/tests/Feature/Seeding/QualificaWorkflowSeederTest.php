<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

// The client's "stati di lavorazione" (spec 0047), transcribed from the
// "Stati di Lavorazione_Commerciale" sheet: one workflow per product category,
// seeded as the last step of QualificaCatalogSeeder.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

/**
 * Merged scenario: QualificaCatalogSeeder costs ~1s per run (thousands of
 * PHP-bound queries), and every check below only reads back the state it
 * leaves behind — none of them mutate it. Instead of paying the seeder once
 * per assertion group, it runs twice total: once for every read-only check,
 * once more to prove the re-run converges (the former idempotency test).
 */
it('seeds catalogue workflows, statuses and criteria per category, and converges on a re-run', function (): void {
    // Step 1: seed once — every single-run check below reads this state.
    test()->seed(QualificaCatalogSeeder::class);

    // was: 'seeds a GOL region column in the sheet order, between the pinned system rows'
    $golWorkflow = QuoteWorkflow::query()->where('name', 'GOL - Lombardia')->firstOrFail();
    $golStatuses = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $golWorkflow->id)
        ->orderBy('sort_order')
        ->get();

    // Every row of the Lombardia column is seeded once: three of them are
    // promoted onto pinned system rows, the rest stay custom. No extra row is
    // added — 'validated' is a group, not a system row (user directive
    // 2026-08-07), and the GOL block classifies no state under it.
    $golAll = WorkflowStatusCatalogue::statusesFor('GOL - Lombardia');
    $golCustom = WorkflowStatusCatalogue::customStatusesFor('GOL - Lombardia');

    expect($golStatuses)->toHaveCount(count($golAll))
        ->and(count($golCustom))->toBe(count($golAll) - 3)
        ->and($golStatuses->first()->system_key)->toBe('open')
        ->and($golStatuses->slice(-2)->pluck('system_key')->all())->toBe(['closed_won', 'closed_lost'])
        ->and($golStatuses->pluck('system_key')->filter()->values()->all())->not->toContain('validated');

    expect($golStatuses->slice(1, count($golCustom))->pluck('name')->values()->all())
        ->toBe(array_column($golCustom, 'name'));

    // First and last custom row of the column, as the sheet lists them —
    // 'Nuovo Contatto' is no longer here: it now labels the pinned open row.
    expect($golStatuses->get(1)->name)->toBe('Da Richiamare')
        ->and($golStatuses->get(count($golCustom))->name)->toBe('In Standby');

    // was: 'labels the pinned system rows with the sheet states, never the generic defaults'
    $pinnedRowsOf = function (string $workflowName): array {
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
    // sheet paints it verde acceso, i.e. the `validated` group, which is
    // pinned to nothing (user directive 2026-08-07), so closed_won falls to
    // the next verde chiaro state.
    expect($pinnedRowsOf('GOL - Lombardia'))->toBe([
        // "Percorso 101" is unfilled in the 2026-09-08 sheet, i.e. OPEN: the
        // first loss of the column is the one below it.
        'closed_lost' => 'Autofinanziato',
        'closed_won' => 'Associato SI _ NOI',
        'open' => 'Nuovo Contatto',
    ]);

    expect($pinnedRowsOf('Consulenza'))->toBe([
        'closed_lost' => 'Persa',
        'closed_won' => 'VINTO',
        'open' => 'Nuovo Contatto',
    ]);

    expect($pinnedRowsOf('Autoimpiego'))->toBe([
        'closed_lost' => 'Non ha i Requisiti',
        'closed_won' => 'Associato SI _ NOI',
        'open' => 'Nuovo Contatto',
    ]);

    expect($pinnedRowsOf('Yisu'))->toBe([
        'closed_lost' => 'Non ha i Requisiti',
        'closed_won' => 'Associato SI _ NOI',
        'open' => 'Nuovo Contatto',
    ]);

    expect($pinnedRowsOf('Autofinanziato'))->toBe([
        'closed_lost' => 'Non risponde',
        'closed_won' => 'OK_Iscritto',
        'open' => 'Nuovo Contatto',
    ]);

    // was: 'promotes a state onto a pinned row instead of duplicating it as a custom one'
    $consulenzaWorkflow = QuoteWorkflow::query()->where('name', 'Consulenza')->firstOrFail();
    $consulenzaStatuses = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $consulenzaWorkflow->id)
        ->get();

    // One row per name, and the promoted ones carry their sheet description
    // and colour onto the system row.
    expect($consulenzaStatuses->pluck('name')->duplicates())->toBeEmpty();

    $vinto = $consulenzaStatuses->firstWhere('name', 'VINTO');

    expect($vinto->system_key)->toBe('closed_won')
        ->and($vinto->color)->toBe('green')
        ->and($vinto->description)->toBe('Trattativa conclusa positivamente.');

    // was: 'seeds Consulenza on the BRANCH criterion, so the states reach the whole branch (user directive 2026-09-01)'
    $consulenzaWorkflowWithCriteria = QuoteWorkflow::query()->where('name', 'Consulenza')->with('criteria')->firstOrFail();
    $consulenzaRoot = ProductCategory::query()->where('name', 'Consulenza')->firstOrFail();

    expect($consulenzaWorkflowWithCriteria->criteria)->toHaveCount(1)
        ->and($consulenzaWorkflowWithCriteria->criteria->first()->field)->toBe('product_category_branch_id')
        ->and($consulenzaWorkflowWithCriteria->criteria->first()->value_id)->toBe($consulenzaRoot->id)
        // The root is a container: an exact-category criterion could never
        // match, since no product sits directly on it.
        ->and($consulenzaRoot->children()->exists())->toBeTrue();

    // was: 'seeds the consulting pick list with the client mapping (user directive 2026-09-01)'
    $consulenzaOrderedStatuses = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $consulenzaWorkflow->id)
        ->orderBy('sort_order')
        ->get();

    // The pinned rows anchor the set: `open` first, the two closed outcomes
    // last, so VINTO/Persa sit at the tail rather than mid-list.
    expect($consulenzaOrderedStatuses->pluck('name')->all())->toBe([
        'Nuovo Contatto',
        'Da Richiamare',
        'In trattativa',
        'Appuntamento Fissato',
        'Rimandata',
        'Annullata',
        'Non risponde',
        'Irreperibile',
        'Non pertinente',
        'Numero inesistente',
        'VINTO',
        'Persa',
    ]);

    expect($consulenzaOrderedStatuses->mapWithKeys(fn (QuoteWorkflowStatus $status): array => [$status->name => $status->group->value])->all())
        ->toBe([
            'Nuovo Contatto' => WorkflowStatusGroup::Open->value,
            'Da Richiamare' => WorkflowStatusGroup::Open->value,
            'In trattativa' => WorkflowStatusGroup::Pending->value,
            'Appuntamento Fissato' => WorkflowStatusGroup::Pending->value,
            'Rimandata' => WorkflowStatusGroup::Open->value,
            'Annullata' => WorkflowStatusGroup::ClosedLost->value,
            'Non risponde' => WorkflowStatusGroup::ClosedLost->value,
            'Irreperibile' => WorkflowStatusGroup::ClosedLost->value,
            'Non pertinente' => WorkflowStatusGroup::ClosedLost->value,
            'Numero inesistente' => WorkflowStatusGroup::ClosedLost->value,
            'VINTO' => WorkflowStatusGroup::ClosedWon->value,
            'Persa' => WorkflowStatusGroup::ClosedLost->value,
        ]);

    // 'In trattativa' is azzurro in the 2026-09-08 sheet, i.e. `pending`: a
    // plain GROUP on a custom row, pinned to nothing. It lost the `validated`
    // classification the retired VALIDATED_STATUSES override gave it.
    expect($consulenzaOrderedStatuses->firstWhere('name', 'In trattativa')->system_key)->toBeNull();

    // was: 'seeds the APL pick list on the APL branch (user directive 2026-09-07)'
    $aplWorkflow = QuoteWorkflow::query()->where('name', 'APL')->with('criteria')->firstOrFail();
    $aplCategory = ProductCategory::query()->where('name', 'APL')->firstOrFail();

    // "APL" is a ROOT that groups its offers: the product sits on its
    // "Orientamento Specialistico" child, so an exact-category criterion would
    // never match a single offer — only the branch one reaches it.
    expect($aplWorkflow->criteria)->toHaveCount(1)
        ->and($aplWorkflow->criteria->first()->field)->toBe('product_category_branch_id')
        ->and($aplWorkflow->criteria->first()->value_id)->toBe($aplCategory->id)
        ->and($aplCategory->parent_id)->toBeNull()
        ->and($aplCategory->children()->pluck('name')->all())->toBe(['Orientamento Specialistico']);

    $aplStatuses = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $aplWorkflow->id)
        ->orderBy('sort_order')
        ->get();

    // The pinned rows anchor the set: `open` first, the two closed outcomes
    // last, so "Assegnato"/"Percorso 101" sit at the tail rather than mid-list.
    expect($aplStatuses->pluck('name')->all())->toBe([
        'Nuovo Contatto',
        'Da Richiamare',
        'Attesa esito SFL/ADI',
        'Attesa _ App. CPI',
        'OK App. Fissato CPI',
        'Autofinanziato',
        'Associato NO _ Altro Ente',
        'NO _ Non ha Requisiti',
        'Frequenta già corso GOL',
        'Non interessato/a',
        'Stato Rinunciatario',
        'Irreperibile',
        'Trasferito altra Sede QG',
        'Non pertinente - Altra regione',
        'Numero Inesistente/Errato',
        'Doppione',
        'Doppione già associato',
        'In Standby',
        'Assegnato',
        'Percorso 101',
    ]);

    // "Assegnato" is the block's ONLY positive outcome, so it takes over the
    // pinned closed_won row; every other closed state is a loss.
    expect($aplStatuses->pluck('group')->map(fn (WorkflowStatusGroup $group): string => $group->value)->countBy()->sortKeys()->all())
        ->toBe([
            WorkflowStatusGroup::ClosedLost->value => 13,
            WorkflowStatusGroup::ClosedWon->value => 1,
            WorkflowStatusGroup::Open->value => 6,
        ]);

    $assegnato = $aplStatuses->firstWhere('name', 'Assegnato');

    expect($assegnato->system_key)->toBe('closed_won')
        ->and($assegnato->color)->toBe('green')
        ->and($aplStatuses->firstWhere('name', 'Percorso 101')->system_key)->toBe('closed_lost')
        ->and($aplStatuses->first()->system_key)->toBe('open');

    // was: 'classifies each status from the sheet legend (2026-09-08 revision)'
    $statusesNamedIn = function (string $workflowName): Collection {
        $workflow = QuoteWorkflow::query()->where('name', $workflowName)->firstOrFail();

        return QuoteWorkflowStatus::query()
            ->where('quote_workflow_id', $workflow->id)
            ->get()
            ->keyBy('name');
    };

    $golLegendStatuses = $statusesNamedIn('GOL - Lombardia');

    // No fill: "Aperto".
    expect($golLegendStatuses['In Standby']->group)->toBe(WorkflowStatusGroup::Open)
        ->and($golLegendStatuses['In Standby']->color)->toBe('slate')
        // Azzurro: "Potenziali Prossimi Associati" — the working phase.
        ->and($golLegendStatuses['Attesa Attivazione DOTE']->group)->toBe(WorkflowStatusGroup::Pending)
        ->and($golLegendStatuses['Attesa Attivazione DOTE']->color)->toBe('blue')
        // Verde chiaro: "Associati del giorno/settimana/mese".
        ->and($golLegendStatuses['Associato SI _ NOI']->group)->toBe(WorkflowStatusGroup::ClosedWon)
        ->and($golLegendStatuses['Associato SI _ NOI']->color)->toBe('green')
        // Pesca: "Chiuso".
        ->and($golLegendStatuses['Irreperibile']->group)->toBe(WorkflowStatusGroup::ClosedLost)
        ->and($golLegendStatuses['Irreperibile']->color)->toBe('red');

    // Verde acceso, the sheet's own "solo per ok da caricare": the ONE state
    // painted `validated`, and a plain custom row — no system key is pinned to
    // that group (user directive 2026-08-07).
    $autoimpiegoLegendStatuses = $statusesNamedIn('Autoimpiego');

    expect($autoimpiegoLegendStatuses['OK_Da Caricare']->group)->toBe(WorkflowStatusGroup::Validated)
        ->and($autoimpiegoLegendStatuses['OK_Da Caricare']->color)->toBe('emerald')
        ->and($autoimpiegoLegendStatuses['OK_Da Caricare']->system_key)->toBeNull();

    // Never note-requiring: the sheet carries no such marker.
    expect($golLegendStatuses->pluck('requires_note')->unique()->all())->toBe([false]);

    // was: 'splits Orientamento from APL-Orientamento, which the sheet paints differently'
    $statusNamedIn = function (string $workflowName, string $statusName): ?QuoteWorkflowStatus {
        $workflow = QuoteWorkflow::query()->where('name', $workflowName)->firstOrFail();

        return QuoteWorkflowStatus::query()
            ->where('quote_workflow_id', $workflow->id)
            ->where('name', $statusName)
            ->first();
    };

    // Campania keeps the sheet's own "APL-Orientamento", pesca: a closed loss.
    expect($statusNamedIn('GOL - Campania', 'APL-Orientamento')->group)->toBe(WorkflowStatusGroup::ClosedLost)
        ->and($statusNamedIn('GOL - Campania', 'Orientamento'))->toBeNull();

    // Lazio and Sicilia call it "Orientamento" and paint it azzurro: pending.
    foreach (['GOL - Lazio', 'GOL - Sicilia'] as $workflowName) {
        expect($statusNamedIn($workflowName, 'Orientamento')->group)->toBe(WorkflowStatusGroup::Pending, $workflowName)
            ->and($statusNamedIn($workflowName, 'APL-Orientamento'))->toBeNull($workflowName);
    }

    // was: 'scopes the descriptions per block, so one name reads differently per category'
    $descriptionOfStatus = function (string $workflowName, string $statusName): string {
        $workflow = QuoteWorkflow::query()->where('name', $workflowName)->firstOrFail();

        return QuoteWorkflowStatus::query()
            ->where('quote_workflow_id', $workflow->id)
            ->where('name', $statusName)
            ->value('description');
    };

    expect($descriptionOfStatus('GOL - Molise', 'Da Richiamare'))
        ->toStartWith('Contatto da ricontattare per completare la lavorazione')
        ->and($descriptionOfStatus('Autoimpiego', 'Da Richiamare'))
        ->toStartWith('Candidato da ricontattare per completare la lavorazione')
        ->and($descriptionOfStatus('Consulenza', 'Da Richiamare'))
        ->toStartWith('Contatto da ricontattare per fornire informazioni');

    // was: 'gives the regions sharing one column the same status list'
    $namesOfColumn = static fn (string $workflowName): array => QuoteWorkflowStatus::query()
        ->whereIn('quote_workflow_id', QuoteWorkflow::query()->where('name', $workflowName)->select('id'))
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    $moliseNames = $namesOfColumn('GOL - Molise');

    // Abruzzo has no column of its own: it was bound to the same list
    // off-sheet (user directive 2026-09-08).
    foreach (['GOL - Puglia', 'GOL - Calabria', 'GOL - Basilicata', 'GOL - Abruzzo'] as $workflowName) {
        expect($namesOfColumn($workflowName))->toBe($moliseNames, $workflowName);
    }

    // Autoimpiego and Yisu share the "uguale per tutte le regioni" block too.
    expect($namesOfColumn('Yisu'))->toBe($namesOfColumn('Autoimpiego'));

    // was: 'leaves the categories absent from the sheet on the global default set'
    // No column in the sheet: no workflow, so their opportunities fall back to
    // the global default set (QuoteWorkflowResolver). "DIL" left this list on
    // 2026-09-10: block 5 of the sheet gave it a column of its own.
    foreach (['Formazione', 'Trattative in Corso', 'Presa Appuntamenti'] as $categoryName) {
        expect(QuoteWorkflow::query()->where('name', $categoryName)->exists())->toBeFalse($categoryName);
    }

    // was: 'seeds the DIL set on the DIL branch, pinned rows around the column'
    $dilCategory = ProductCategory::query()->where('name', 'DIL')->firstOrFail();
    $dilWorkflow = QuoteWorkflow::query()->where('name', 'DIL')->with('criteria')->firstOrFail();

    // Matched on the whole BRANCH, like Consulenza/APL (user directive
    // 2026-09-17): DIL is a container, its courses sit on "DIL - Lombardia".
    expect($dilWorkflow->criteria)->toHaveCount(1)
        ->and($dilWorkflow->criteria->first()->field)->toBe(WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD)
        ->and($dilWorkflow->criteria->first()->value_id)->toBe($dilCategory->id);

    $dilStatuses = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $dilWorkflow->id)
        ->orderBy('sort_order')
        ->get();

    $dilAll = WorkflowStatusCatalogue::statusesFor('DIL');
    $dilCustom = WorkflowStatusCatalogue::customStatusesFor('DIL');

    // Every row of the column once — three promoted onto the pinned system
    // rows, the rest custom between them. The column classifies nothing as
    // validated, which is a group and not a system row.
    expect($dilStatuses)->toHaveCount(count($dilAll))
        ->and(count($dilCustom))->toBe(count($dilAll) - 3)
        ->and($dilStatuses->first()->system_key)->toBe('open')
        ->and($dilStatuses->first()->name)->toBe('Nuovo Contatto')
        ->and($dilStatuses->slice(-2)->pluck('system_key')->all())->toBe(['closed_won', 'closed_lost'])
        ->and($dilStatuses->slice(-2)->pluck('name')->all())->toBe(['Associato SI _ NOI', 'Non interessato/a'])
        ->and($dilStatuses->pluck('system_key')->filter()->values()->all())->not->toContain('validated');

    expect($dilStatuses->slice(1, count($dilCustom))->pluck('name')->values()->all())
        ->toBe(array_column($dilCustom, 'name'));

    // The CPI confirmation every region carries is absent from this column.
    expect($dilStatuses->pluck('name')->all())->not->toContain('OK App. Fissato CPI');

    // Step 2: re-seed — natural key (name / signature), no duplicates.
    // was: 'provisions one active workflow per catalogue category, idempotently'
    test()->seed(QualificaCatalogSeeder::class);

    $expectedCategories = array_keys(WorkflowStatusCatalogue::WORKFLOWS);

    expect(QuoteWorkflow::query()->count())->toBe(count($expectedCategories));

    foreach ($expectedCategories as $categoryName) {
        $reseededWorkflow = QuoteWorkflow::query()->where('name', $categoryName)->with('criteria')->first();
        $reseededCategory = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

        expect($reseededWorkflow)->not->toBeNull($categoryName)
            ->and($reseededWorkflow->is_active)->toBeTrue($categoryName)
            // Matched on its own category alone — by exact category, or by
            // whole branch for the categories that declare it (spec 0092).
            ->and($reseededWorkflow->criteria)->toHaveCount(1, $categoryName)
            ->and($reseededWorkflow->criteria->first()->field)
            ->toBe(WorkflowStatusCatalogue::criterionFieldFor($categoryName), $categoryName)
            ->and($reseededWorkflow->criteria->first()->value_id)->toBe($reseededCategory->id, $categoryName);
    }
});

it('transcribes the DIL column of the sheet, its duplicated row folded', function (): void {
    // Pure transcription check, before anything is seeded: block 5 of the
    // sheet, in the client's own order, with the colours sampled off it —
    // azzurro pending, verde chiaro the single positive outcome, pesca the
    // closures, no fill open. The column lists "OK App. Fissato APL" twice:
    // folded to one (user directive 2026-09-10).
    $transcribed = array_map(
        static fn (array $status): array => [$status['name'], $status['group']],
        WorkflowStatusCatalogue::statusesFor('DIL'),
    );

    expect($transcribed)->toBe([
        ['Nuovo Contatto', WorkflowStatusGroup::Open->value],
        ['Da Richiamare', WorkflowStatusGroup::Open->value],
        ['Attesa esito SFL/ADI', WorkflowStatusGroup::Open->value],
        ['Attesa _ App. CPI', WorkflowStatusGroup::Pending->value],
        ['Attesa _ App. APL', WorkflowStatusGroup::Pending->value],
        ['OK App. Fissato APL', WorkflowStatusGroup::Pending->value],
        ['Attesa Attivazione DOTE', WorkflowStatusGroup::Pending->value],
        ['Attesa Iscrizione SIUF', WorkflowStatusGroup::Pending->value],
        ['In attesa aggancio BES', WorkflowStatusGroup::Pending->value],
        ['Associato SI _ NOI', WorkflowStatusGroup::ClosedWon->value],
        ['Non interessato/a', WorkflowStatusGroup::ClosedLost->value],
        ['Stato Rinunciatario', WorkflowStatusGroup::ClosedLost->value],
        ['Numero Inesistente/Errato', WorkflowStatusGroup::ClosedLost->value],
        ['Associato NO _ Altro Ente', WorkflowStatusGroup::ClosedLost->value],
        ['NO _ Non ha Requisiti', WorkflowStatusGroup::ClosedLost->value],
        ['Irreperibile', WorkflowStatusGroup::ClosedLost->value],
        ['Doppione già associato', WorkflowStatusGroup::ClosedLost->value],
        ['Doppione', WorkflowStatusGroup::ClosedLost->value],
        ['Frequenta già corso GOL', WorkflowStatusGroup::ClosedLost->value],
        ['Non pertinente - Altra regione', WorkflowStatusGroup::ClosedLost->value],
        ['Trasferito altra Sede QG', WorkflowStatusGroup::ClosedLost->value],
        ['Autofinanziato', WorkflowStatusGroup::ClosedLost->value],
        ['In Standby', WorkflowStatusGroup::Open->value],
    ]);
});
