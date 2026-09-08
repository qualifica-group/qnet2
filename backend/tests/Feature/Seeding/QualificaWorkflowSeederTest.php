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
            // Matched on its own category alone — by exact category, or by
            // whole branch for the categories that declare it (spec 0092).
            ->and($workflow->criteria)->toHaveCount(1, $categoryName)
            ->and($workflow->criteria->first()->field)
            ->toBe(WorkflowStatusCatalogue::criterionFieldFor($categoryName), $categoryName)
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
    // sheet paints it verde acceso, i.e. the `validated` group, which is
    // pinned to nothing (user directive 2026-08-07), so closed_won falls to
    // the next verde chiaro state.
    expect($pinned('GOL - Lombardia'))->toBe([
        // "Percorso 101" is unfilled in the 2026-09-08 sheet, i.e. OPEN: the
        // first loss of the column is the one below it.
        'closed_lost' => 'Autofinanziato',
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
        'closed_lost' => 'Non risponde',
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

it('seeds Consulenza on the BRANCH criterion, so the states reach the whole branch (user directive 2026-09-01)', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $workflow = QuoteWorkflow::query()->where('name', 'Consulenza')->with('criteria')->firstOrFail();
    $root = ProductCategory::query()->where('name', 'Consulenza')->firstOrFail();

    expect($workflow->criteria)->toHaveCount(1)
        ->and($workflow->criteria->first()->field)->toBe('product_category_branch_id')
        ->and($workflow->criteria->first()->value_id)->toBe($root->id)
        // The root is a container: an exact-category criterion could never
        // match, since no product sits directly on it.
        ->and($root->children()->exists())->toBeTrue();
});

it('seeds the consulting pick list with the client mapping (user directive 2026-09-01)', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $workflow = QuoteWorkflow::query()->where('name', 'Consulenza')->firstOrFail();
    $statuses = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $workflow->id)
        ->orderBy('sort_order')
        ->get();

    // The pinned rows anchor the set: `open` first, the two closed outcomes
    // last, so VINTO/Persa sit at the tail rather than mid-list.
    expect($statuses->pluck('name')->all())->toBe([
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

    expect($statuses->mapWithKeys(fn (QuoteWorkflowStatus $status): array => [$status->name => $status->group->value])->all())
        ->toBe([
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
    expect($statuses->firstWhere('name', 'In trattativa')->system_key)->toBeNull();
});

it('seeds the APL pick list on the APL branch (user directive 2026-09-07)', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $workflow = QuoteWorkflow::query()->where('name', 'APL')->with('criteria')->firstOrFail();
    $category = ProductCategory::query()->where('name', 'APL')->firstOrFail();

    // "APL" is a ROOT that groups its offers: the product sits on its
    // "Orientamento Specialistico" child, so an exact-category criterion would
    // never match a single offer — only the branch one reaches it.
    expect($workflow->criteria)->toHaveCount(1)
        ->and($workflow->criteria->first()->field)->toBe('product_category_branch_id')
        ->and($workflow->criteria->first()->value_id)->toBe($category->id)
        ->and($category->parent_id)->toBeNull()
        ->and($category->children()->pluck('name')->all())->toBe(['Orientamento Specialistico']);

    $statuses = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $workflow->id)
        ->orderBy('sort_order')
        ->get();

    // The pinned rows anchor the set: `open` first, the two closed outcomes
    // last, so "Assegnato"/"Percorso 101" sit at the tail rather than mid-list.
    expect($statuses->pluck('name')->all())->toBe([
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
    expect($statuses->pluck('group')->map(fn (WorkflowStatusGroup $group): string => $group->value)->countBy()->sortKeys()->all())
        ->toBe([
            WorkflowStatusGroup::ClosedLost->value => 13,
            WorkflowStatusGroup::ClosedWon->value => 1,
            WorkflowStatusGroup::Open->value => 5,
        ]);

    $assegnato = $statuses->firstWhere('name', 'Assegnato');

    expect($assegnato->system_key)->toBe('closed_won')
        ->and($assegnato->color)->toBe('green')
        ->and($statuses->firstWhere('name', 'Percorso 101')->system_key)->toBe('closed_lost')
        ->and($statuses->first()->system_key)->toBe('open');
});

it('classifies each status from the sheet legend (2026-09-08 revision)', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $byName = function (string $workflowName): Collection {
        $workflow = QuoteWorkflow::query()->where('name', $workflowName)->firstOrFail();

        return QuoteWorkflowStatus::query()
            ->where('quote_workflow_id', $workflow->id)
            ->get()
            ->keyBy('name');
    };

    $statuses = $byName('GOL - Lombardia');

    // No fill: "Aperto".
    expect($statuses['In Standby']->group)->toBe(WorkflowStatusGroup::Open)
        ->and($statuses['In Standby']->color)->toBe('slate')
        // Azzurro: "Potenziali Prossimi Associati" — the working phase.
        ->and($statuses['Attesa Attivazione DOTE']->group)->toBe(WorkflowStatusGroup::Pending)
        ->and($statuses['Attesa Attivazione DOTE']->color)->toBe('blue')
        // Verde chiaro: "Associati del giorno/settimana/mese".
        ->and($statuses['Associato SI _ NOI']->group)->toBe(WorkflowStatusGroup::ClosedWon)
        ->and($statuses['Associato SI _ NOI']->color)->toBe('green')
        // Pesca: "Chiuso".
        ->and($statuses['Irreperibile']->group)->toBe(WorkflowStatusGroup::ClosedLost)
        ->and($statuses['Irreperibile']->color)->toBe('red');

    // Verde acceso, the sheet's own "solo per ok da caricare": the ONE state
    // painted `validated`, and a plain custom row — no system key is pinned to
    // that group (user directive 2026-08-07).
    $autoimpiego = $byName('Autoimpiego');

    expect($autoimpiego['OK_Da Caricare']->group)->toBe(WorkflowStatusGroup::Validated)
        ->and($autoimpiego['OK_Da Caricare']->color)->toBe('emerald')
        ->and($autoimpiego['OK_Da Caricare']->system_key)->toBeNull();

    // Never note-requiring: the sheet carries no such marker.
    expect($statuses->pluck('requires_note')->unique()->all())->toBe([false]);
});

it('splits Orientamento from APL-Orientamento, which the sheet paints differently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $status = function (string $workflowName, string $statusName): ?QuoteWorkflowStatus {
        $workflow = QuoteWorkflow::query()->where('name', $workflowName)->firstOrFail();

        return QuoteWorkflowStatus::query()
            ->where('quote_workflow_id', $workflow->id)
            ->where('name', $statusName)
            ->first();
    };

    // Campania keeps the sheet's own "APL-Orientamento", pesca: a closed loss.
    expect($status('GOL - Campania', 'APL-Orientamento')->group)->toBe(WorkflowStatusGroup::ClosedLost)
        ->and($status('GOL - Campania', 'Orientamento'))->toBeNull();

    // Lazio and Sicilia call it "Orientamento" and paint it azzurro: pending.
    foreach (['GOL - Lazio', 'GOL - Sicilia'] as $workflowName) {
        expect($status($workflowName, 'Orientamento')->group)->toBe(WorkflowStatusGroup::Pending, $workflowName)
            ->and($status($workflowName, 'APL-Orientamento'))->toBeNull($workflowName);
    }
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

it('gives the regions sharing one column the same status list', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $names = static fn (string $workflowName): array => QuoteWorkflowStatus::query()
        ->whereIn('quote_workflow_id', QuoteWorkflow::query()->where('name', $workflowName)->select('id'))
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    $molise = $names('GOL - Molise');

    // Abruzzo has no column of its own: it was bound to the same list
    // off-sheet (user directive 2026-09-08).
    foreach (['GOL - Puglia', 'GOL - Calabria', 'GOL - Basilicata', 'GOL - Abruzzo'] as $workflowName) {
        expect($names($workflowName))->toBe($molise, $workflowName);
    }

    // Autoimpiego and Yisu share the "uguale per tutte le regioni" block too.
    expect($names('Yisu'))->toBe($names('Autoimpiego'));
});

it('leaves the categories absent from the sheet on the global default set', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // No column in the sheet: no workflow, so their opportunities fall back to
    // the global default set (QuoteWorkflowResolver).
    foreach (['DIL', 'Formazione', 'Trattative in Corso', 'Presa Appuntamenti'] as $categoryName) {
        expect(QuoteWorkflow::query()->where('name', $categoryName)->exists())->toBeFalse($categoryName);
    }
});
