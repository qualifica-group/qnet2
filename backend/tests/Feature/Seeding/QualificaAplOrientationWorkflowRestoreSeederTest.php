<?php

use App\Enums\QuoteLineType;
use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use Database\Seeders\QualificaAplOrientationWorkflowRestoreSeeder;
use Database\Seeders\QualificaCatalog\AplOrientationRequestCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

/**
 * The Orientamento specialistico workflow deleted on 2026-11-09 comes back with
 * its own ids, replacing the one created in its place, and its requests go back
 * on the status they had.
 */
uses(RefreshDatabase::class);

const RESTORED_OPEN_ID = 354;

const RESTORED_PENDING_CPI_ID = 356;

const RESTORED_APPOINTMENT_ID = 357;

const RESTORED_COMPLETED_ID = 371;

beforeEach(function (): void {
    $this->category = ProductCategory::factory()->create(['name' => AplOrientationRequestCatalogue::CATEGORY]);
    $this->replacement = QuoteWorkflow::factory()->create([
        'name' => AplOrientationRequestCatalogue::CATEGORY,
        'criteria_signature' => 'product_category_id:'.$this->category->id,
    ]);

    $status = fn (string $name, array $state = []): QuoteWorkflowStatus => QuoteWorkflowStatus::factory()->create(['quote_workflow_id' => $this->replacement->id, 'name' => $name, ...$state]);

    $this->newContact = QuoteWorkflowStatus::factory()->system('open')->create(['quote_workflow_id' => $this->replacement->id, 'name' => 'Nuovo Contatto']);
    $this->callback = $status('Da Richiamare');
    $this->appointment = $status('Appuntamento Fissato');
    $this->negotiation = $status('In trattativa');
    $this->won = QuoteWorkflowStatus::factory()->system('closed_won')->create(['quote_workflow_id' => $this->replacement->id, 'name' => 'VINTO']);
    $this->otherOpen = QuoteWorkflowStatus::factory()->system('open')->create(['name' => 'Nuovo Contatto']);
    $this->otherPending = QuoteWorkflowStatus::factory()->create(['quote_workflow_id' => $this->otherOpen->quote_workflow_id, 'name' => 'Assegnato']);

    $this->requestOn = function (int $statusId, array $loggedStatusIds = [], ?ProductCategory $category = null): Quote {
        $opportunity = Opportunity::factory()->create();
        $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $statusId]);
        QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => QuoteLineType::Revenue,
            'product_id' => Product::factory()->create(['category_id' => ($category ?? $this->category)->id])->id,
        ]);

        foreach ($loggedStatusIds as $loggedStatusId) {
            activity('opportunities')->performedOn($opportunity)->event('updated')
                ->withProperties(['attributes' => ['quote_workflow_status_id' => $loggedStatusId], 'old' => ['quote_workflow_status_id' => null]])
                ->log('Quote working status change');
        }

        return $quote;
    };
});

it('brings the deleted workflow back with its ids and removes its replacement', function (): void {
    $this->seed(QualificaAplOrientationWorkflowRestoreSeeder::class);

    $workflow = QuoteWorkflow::query()->with('criteria', 'statuses')->findOrFail(20);

    expect($workflow->name)->toBe(AplOrientationRequestCatalogue::CATEGORY)
        ->and($workflow->is_active)->toBeTrue()
        ->and($workflow->criteria_signature)->toBe('product_category_id:'.$this->category->id)
        ->and($workflow->criteria->pluck('value_id', 'field')->all())->toBe(['product_category_id' => $this->category->id])
        ->and($workflow->statuses->pluck('id')->sort()->values()->all())->toBe(range(354, 372))
        ->and($workflow->statuses->firstWhere('id', RESTORED_OPEN_ID)->system_key)->toBe('open')
        ->and($workflow->statuses->firstWhere('id', 366)->group)->toBe(WorkflowStatusGroup::Validated)
        ->and($workflow->statuses->firstWhere('id', RESTORED_COMPLETED_ID)->name)->toBe('Fine pratica')
        ->and(QuoteWorkflow::query()->find($this->replacement->id))->toBeNull()
        ->and(QuoteWorkflowStatus::query()->find($this->newContact->id))->toBeNull();
});

it('puts a reset request back on the status it last had, with an authorless log entry', function (): void {
    $quote = ($this->requestOn)($this->otherOpen->id, [RESTORED_OPEN_ID, RESTORED_APPOINTMENT_ID]);

    $this->seed(QualificaAplOrientationWorkflowRestoreSeeder::class);

    expect($quote->fresh()->quote_workflow_status_id)->toBe(RESTORED_APPOINTMENT_ID);

    $entry = Activity::query()->where('subject_type', 'opportunity')->where('subject_id', $quote->opportunity_id)->latest('id')->first();

    expect($entry->description)->toBe('Quote working status restored')
        ->and($entry->causer_id)->toBeNull()
        ->and($entry->properties['old']['quote_workflow_status_id'])->toBe($this->otherOpen->id)
        ->and($entry->properties['attributes']['quote_workflow_status_id'])->toBe(RESTORED_APPOINTMENT_ID);
});

it('carries over the status an operator chose on the replacement workflow', function (): void {
    $callback = ($this->requestOn)($this->callback->id, [RESTORED_PENDING_CPI_ID, $this->callback->id]);
    $appointment = ($this->requestOn)($this->appointment->id, [$this->appointment->id]);
    $won = ($this->requestOn)($this->won->id, [$this->won->id]);
    $noEquivalent = ($this->requestOn)($this->negotiation->id, [RESTORED_PENDING_CPI_ID, $this->negotiation->id]);

    $this->seed(QualificaAplOrientationWorkflowRestoreSeeder::class);

    expect($callback->fresh()->quote_workflow_status_id)->toBe(RESTORED_OPEN_ID)
        ->and($appointment->fresh()->quote_workflow_status_id)->toBe(RESTORED_APPOINTMENT_ID)
        ->and($won->fresh()->quote_workflow_status_id)->toBe(RESTORED_COMPLETED_ID)
        ->and($noEquivalent->fresh()->quote_workflow_status_id)->toBe(RESTORED_PENDING_CPI_ID);
});

it('starts from the initial status of the workflow when the log has nothing to restore', function (): void {
    $quote = ($this->requestOn)($this->otherOpen->id);

    $this->seed(QualificaAplOrientationWorkflowRestoreSeeder::class);

    expect($quote->fresh()->quote_workflow_status_id)->toBe(RESTORED_OPEN_ID);
});

it('leaves alone the requests the reset did not touch', function (): void {
    $elsewhere = ($this->requestOn)($this->otherOpen->id, [RESTORED_APPOINTMENT_ID], ProductCategory::factory()->create());
    $otherStatus = ($this->requestOn)($this->otherPending->id, [RESTORED_APPOINTMENT_ID]);

    $this->seed(QualificaAplOrientationWorkflowRestoreSeeder::class);

    expect($elsewhere->fresh()->quote_workflow_status_id)->toBe($this->otherOpen->id)
        ->and($otherStatus->fresh()->quote_workflow_status_id)->toBe($this->otherPending->id);
});

it('writes nothing on a second run', function (): void {
    ($this->requestOn)($this->otherOpen->id, [RESTORED_APPOINTMENT_ID]);

    $this->seed(QualificaAplOrientationWorkflowRestoreSeeder::class);
    $statuses = QuoteWorkflowStatus::query()->count();
    $entries = Activity::query()->count();

    $this->seed(QualificaAplOrientationWorkflowRestoreSeeder::class);

    expect(QuoteWorkflowStatus::query()->count())->toBe($statuses)
        ->and(Activity::query()->count())->toBe($entries);
});
