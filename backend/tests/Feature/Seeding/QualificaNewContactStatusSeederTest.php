<?php

declare(strict_types=1);

use App\Enums\ReportStatusColumn;
use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\ReportColumnStatus;
use Database\Seeders\QualificaNewContactStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// User directive 2026-11-12 (spec 0391): every status set starts with "Nuovo Contatto".

uses(RefreshDatabase::class);

/**
 * A workflow whose initial status has a meaning of its own, like Orientamento specialistico.
 *
 * @return array{0: QuoteWorkflow, 1: QuoteWorkflowStatus, 2: QuoteWorkflowStatus, 3: QuoteWorkflowStatus}
 */
function workflowWithoutNewContact(string $name = 'Orientamento specialistico'): array
{
    $workflow = QuoteWorkflow::factory()->create(['name' => $name]);
    $status = static fn (string $name, WorkflowStatusGroup $group, int $sort, ?string $key = null): QuoteWorkflowStatus => QuoteWorkflowStatus::factory()->create([
        'quote_workflow_id' => $workflow->id, 'name' => $name, 'group' => $group, 'sort_order' => $sort, 'system_key' => $key,
    ]);

    return [
        $workflow,
        $status('Da Richiamare', WorkflowStatusGroup::Open, 0, WorkflowStatusSystemKey::Open->value),
        $status('Da convocare', WorkflowStatusGroup::Validated, 10),
        $status('Fine pratica', WorkflowStatusGroup::ClosedWon, 20, WorkflowStatusSystemKey::ClosedWon->value),
    ];
}

function orderedNames(?int $workflowId): array
{
    return QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflowId)->orderBy('sort_order')->pluck('name')->all();
}

it('puts a new initial "Nuovo Contatto" before a meaningful initial status, which keeps its requests', function (): void {
    [$workflow, $previousOpen, $custom, $won] = workflowWithoutNewContact();
    $quote = Quote::factory()->create();
    $quote->forceFill(['quote_workflow_status_id' => $previousOpen->id])->saveQuietly();

    $this->seed(QualificaNewContactStatusSeeder::class);

    $newContact = QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflow->id)->where('name', 'Nuovo Contatto')->sole();
    expect($newContact->system_key)->toBe(WorkflowStatusSystemKey::Open->value)
        ->and($newContact->group)->toBe(WorkflowStatusGroup::Open)
        ->and($previousOpen->fresh()->system_key)->toBeNull()
        ->and($previousOpen->fresh()->name)->toBe('Da Richiamare')
        ->and($quote->fresh()->quote_workflow_status_id)->toBe($previousOpen->id)
        ->and(orderedNames($workflow->id))->toBe(['Nuovo Contatto', 'Da Richiamare', 'Da convocare', 'Fine pratica'])
        ->and($custom->fresh()->system_key)->toBeNull()
        ->and($won->fresh()->system_key)->toBe(WorkflowStatusSystemKey::ClosedWon->value);
});

it('renames the generic "Aperta" of the default set, so its requests stay new contacts', function (): void {
    $aperta = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', WorkflowStatusSystemKey::Open->value)->sole();
    $aperta->forceFill(['name' => 'Aperta'])->save();
    $count = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->count();

    $this->seed(QualificaNewContactStatusSeeder::class);

    expect($aperta->fresh()->name)->toBe('Nuovo Contatto')
        ->and($aperta->fresh()->system_key)->toBe(WorkflowStatusSystemKey::Open->value)
        ->and(QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->count())->toBe($count);
});

it('leaves alone a set that already has "Nuovo Contatto", and a second run writes nothing', function (): void {
    $workflow = QuoteWorkflow::factory()->create(['name' => 'ISO']);
    $existing = QuoteWorkflowStatus::factory()->create([
        'quote_workflow_id' => $workflow->id, 'name' => 'nuovo contatto', 'group' => WorkflowStatusGroup::Open,
        'system_key' => WorkflowStatusSystemKey::Open->value, 'sort_order' => 0,
    ]);
    workflowWithoutNewContact();

    $this->seed(QualificaNewContactStatusSeeder::class);
    $snapshot = QuoteWorkflowStatus::query()->orderBy('id')->get(['id', 'name', 'system_key', 'sort_order'])->toArray();
    $this->seed(QualificaNewContactStatusSeeder::class);

    expect(QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflow->id)->pluck('id')->all())->toBe([$existing->id])
        ->and(QuoteWorkflowStatus::query()->orderBy('id')->get(['id', 'name', 'system_key', 'sort_order'])->toArray())->toBe($snapshot);
});

it('adds the new status to "Nuovi contatti non gestiti" only when the statistics are already configured', function (): void {
    [$workflow] = workflowWithoutNewContact();
    $this->seed(QualificaNewContactStatusSeeder::class);
    expect(ReportColumnStatus::query()->exists())->toBeFalse();

    [$second] = workflowWithoutNewContact('Apprendistato');
    configureReportColumn(ReportStatusColumn::Potentials, [QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflow->id)->where('name', 'Da convocare')->sole()]);
    $this->seed(QualificaNewContactStatusSeeder::class);

    $newContact = QuoteWorkflowStatus::query()->where('quote_workflow_id', $second->id)->where('name', 'Nuovo Contatto')->sole();
    expect(ReportColumnStatus::query()->where('column_key', ReportStatusColumn::NewContacts->value)->pluck('quote_workflow_status_id')->all())
        ->toBe([$newContact->id]);
});
