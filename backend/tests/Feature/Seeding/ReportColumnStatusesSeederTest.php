<?php

declare(strict_types=1);

use App\Enums\ReportStatusColumn;
use App\Enums\WorkflowStatusGroup;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\ReportColumnStatus;
use Database\Seeders\ReportColumnStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Spec 0391 AC-010: the one-shot first configuration of the statistics columns.

uses(RefreshDatabase::class);

function seederStatus(?QuoteWorkflow $workflow, WorkflowStatusGroup $group, string $name, ?string $systemKey = null): QuoteWorkflowStatus
{
    return QuoteWorkflowStatus::factory()->create([
        'quote_workflow_id' => $workflow?->id,
        'group' => $group,
        'name' => $name,
        'system_key' => $systemKey,
    ]);
}

/**
 * @return array<int, int> ids chosen for the column, ascending
 */
function seederChosen(ReportStatusColumn $column): array
{
    return ReportColumnStatus::query()
        ->where('column_key', $column->value)
        ->orderBy('quote_workflow_status_id')
        ->pluck('quote_workflow_status_id')
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();
}

it('AC-010: applies the rules of spec 0391 D-6 to every workflow and to the default set', function (): void {
    $default = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->get()->pluck('id', 'group.value');

    $standard = QuoteWorkflow::factory()->create(['name' => 'Consulenza']);
    $standardNew = seederStatus($standard, WorkflowStatusGroup::Open, 'Nuovo Contatto');
    $standardOther = seederStatus($standard, WorkflowStatusGroup::Open, 'Da richiamare');
    $standardPending = seederStatus($standard, WorkflowStatusGroup::Pending, 'In trattativa');
    $standardValidated = seederStatus($standard, WorkflowStatusGroup::Validated, 'Validato custom');
    $standardWon = seederStatus($standard, WorkflowStatusGroup::ClosedWon, 'Associato');
    $standardLost = seederStatus($standard, WorkflowStatusGroup::ClosedLost, 'Perso');

    $restricted = QuoteWorkflow::factory()->create(['name' => 'ISO']);
    $restrictedNew = seederStatus($restricted, WorkflowStatusGroup::Open, 'Nuovo Contatto');
    $restrictedNegotiation = seederStatus($restricted, WorkflowStatusGroup::Pending, 'In trattativa');
    $restrictedWaiting = seederStatus($restricted, WorkflowStatusGroup::Pending, 'Attesa documenti');
    $restrictedValidated = seederStatus($restricted, WorkflowStatusGroup::Validated, 'Validato ISO');
    $restrictedWon = seederStatus($restricted, WorkflowStatusGroup::ClosedWon, 'Chiusa SI');

    $aplLike = QuoteWorkflow::factory()->create(['name' => 'apl']);
    $aplWaiting = seederStatus($aplLike, WorkflowStatusGroup::Pending, 'Attesa APL');

    $guidance = QuoteWorkflow::factory()->create(['name' => 'Orientamento specialistico']);
    $guidanceSummon = seederStatus($guidance, WorkflowStatusGroup::ClosedWon, 'Da convocare');
    $guidanceFileClosed = seederStatus($guidance, WorkflowStatusGroup::ClosedWon, 'Fine pratica');
    $guidanceOpen = seederStatus($guidance, WorkflowStatusGroup::Open, 'Aperta orientamento');

    $this->seed(ReportColumnStatusesSeeder::class);

    $sorted = static function (array $ids): array {
        sort($ids);

        return $ids;
    };

    // Nuovo Contatto of each workflow + the default set's initial status; nothing for a workflow without it.
    expect(seederChosen(ReportStatusColumn::NewContacts))->toBe($sorted([$standardNew->id, $restrictedNew->id, $default['open']]))
        ->and(seederChosen(ReportStatusColumn::NewContacts))->not->toContain($standardOther->id, $guidanceOpen->id);

    // Pending/validated, except the restricted workflows where only "In trattativa" counts.
    $potentials = seederChosen(ReportStatusColumn::Potentials);
    expect($potentials)->toBe($sorted([$standardPending->id, $standardValidated->id, $restrictedNegotiation->id, $default['validated']]))
        ->and($potentials)->not->toContain($restrictedWaiting->id, $restrictedValidated->id, $aplWaiting->id);

    // Closed positive for associati and trattative concluse (Orientamento keeps "Fine pratica" among them).
    $won = $sorted([$standardWon->id, $restrictedWon->id, $guidanceSummon->id, $guidanceFileClosed->id, $default['closed_won']]);
    expect(seederChosen(ReportStatusColumn::Associated))->toBe($won)
        ->and(seederChosen(ReportStatusColumn::ConcludedDeals))->toBe($won)
        ->and(seederChosen(ReportStatusColumn::ConcludedDeals))->toContain($guidanceFileClosed->id);

    // Invio presa in carico: closed positive, but only "Da convocare" for Orientamento specialistico.
    $intake = seederChosen(ReportStatusColumn::IntakeSent);
    expect($intake)->toBe($sorted([$standardWon->id, $restrictedWon->id, $guidanceSummon->id, $default['closed_won']]))
        ->and($intake)->not->toContain($guidanceFileClosed->id, $standardLost->id);
});

it('AC-010: a second run over an existing configuration changes nothing', function (): void {
    $workflow = QuoteWorkflow::factory()->create(['name' => 'Consulenza']);
    $chosen = seederStatus($workflow, WorkflowStatusGroup::Pending, 'Scelto a mano');
    $later = seederStatus($workflow, WorkflowStatusGroup::ClosedWon, 'Creato dopo');
    ReportColumnStatus::query()->create(['column_key' => ReportStatusColumn::Associated->value, 'quote_workflow_status_id' => $chosen->id]);

    $this->seed(ReportColumnStatusesSeeder::class);

    expect(ReportColumnStatus::query()->count())->toBe(1)
        ->and(seederChosen(ReportStatusColumn::Associated))->toBe([$chosen->id])
        ->and(seederChosen(ReportStatusColumn::IntakeSent))->not->toContain($later->id);

    // And a re-run right after a first full run keeps the very same rows.
    ReportColumnStatus::query()->delete();
    $this->seed(ReportColumnStatusesSeeder::class);
    $snapshot = ReportColumnStatus::query()->orderBy('id')->get(['column_key', 'quote_workflow_status_id'])->toArray();

    $this->seed(ReportColumnStatusesSeeder::class);

    expect(ReportColumnStatus::query()->orderBy('id')->get(['column_key', 'quote_workflow_status_id'])->toArray())->toBe($snapshot);
});
