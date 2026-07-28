<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Models\ProductCategory;
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

    expect(OpportunityWorkflow::query()->count())->toBe(count($expected));

    foreach ($expected as $categoryName) {
        $workflow = OpportunityWorkflow::query()->where('name', $categoryName)->with('criteria')->first();
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

    $workflow = OpportunityWorkflow::query()->where('name', 'GOL - Lombardia')->firstOrFail();
    $statuses = OpportunityWorkflowStatus::query()
        ->where('opportunity_workflow_id', $workflow->id)
        ->orderBy('sort_order')
        ->get();

    // The 25 rows of the Lombardia column, plus the 4 rows every set is
    // pinned with (WorkflowStatusWriter): 'Aperta' first, the closing trio last.
    $custom = WorkflowStatusCatalogue::statusesFor('GOL - Lombardia');

    expect($statuses)->toHaveCount(count($custom) + 4)
        ->and($statuses->first()->system_key)->toBe('open')
        ->and($statuses->slice(-3)->pluck('system_key')->all())->toBe(['validated', 'closed_won', 'closed_lost']);

    expect($statuses->slice(1, count($custom))->pluck('name')->values()->all())
        ->toBe(array_column($custom, 'name'));

    // First and last custom row of the column, as the sheet lists them.
    expect($statuses->get(1)->name)->toBe('Da Richiamare')
        ->and($statuses->get(count($custom))->name)->toBe('In Standby');
});

it('classifies each status from the sheet legend', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $workflow = OpportunityWorkflow::query()->where('name', 'GOL - Lombardia')->firstOrFail();
    $statuses = OpportunityWorkflowStatus::query()
        ->where('opportunity_workflow_id', $workflow->id)
        ->whereNull('system_key')
        ->get()
        ->keyBy('name');

    // No fill: "stato di lavorazione aperto".
    expect($statuses['Da Richiamare']->group)->toBe(WorkflowStatusGroup::Open)
        ->and($statuses['Da Richiamare']->color)->toBe('slate')
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
        $workflow = OpportunityWorkflow::query()->where('name', $workflowName)->firstOrFail();

        return OpportunityWorkflowStatus::query()
            ->where('opportunity_workflow_id', $workflow->id)
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

    $names = static fn (string $workflowName): array => OpportunityWorkflowStatus::query()
        ->whereIn('opportunity_workflow_id', OpportunityWorkflow::query()->where('name', $workflowName)->select('id'))
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
    // the global default set (OpportunityWorkflowResolver).
    foreach (['GOL - Abruzzo', 'DIL', 'Formazione', 'Trattative in Corso', 'Presa Appuntamenti'] as $categoryName) {
        expect(OpportunityWorkflow::query()->where('name', $categoryName)->exists())->toBeFalse($categoryName);
    }
});
