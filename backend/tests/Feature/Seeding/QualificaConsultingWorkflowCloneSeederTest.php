<?php

use App\DataObjects\QuoteWorkflows\CreateQuoteWorkflowData;
use App\Enums\WorkflowStatusGroup;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Services\QuoteWorkflowService;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Database\Seeders\QualificaConsultingWorkflowCloneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Spec 0354: the "Consulenza" stati offerta workflow copied on every new root
// category without one, "Formazione" aside.
uses(RefreshDatabase::class);

function cloneTestWorkflow(string $name, string $field, int $categoryId, bool $isActive = true): QuoteWorkflow
{
    return app(QuoteWorkflowService::class)->create(new CreateQuoteWorkflowData(
        name: $name,
        isActive: $isActive,
        criteria: [['field' => $field, 'value_id' => $categoryId]],
        statuses: [
            ['name' => 'In trattativa', 'description' => 'Trattativa aperta', 'color' => 'amber', 'group' => WorkflowStatusGroup::Pending->value, 'requires_note' => false],
            ['name' => 'Validato', 'description' => null, 'color' => 'blue', 'group' => WorkflowStatusGroup::Validated->value, 'requires_note' => true],
        ],
        openStatus: ['name' => 'Nuova richiesta', 'description' => 'Appena arrivata', 'color' => 'slate', 'requires_note' => false],
        closedWonStatus: ['name' => 'Vinta', 'description' => null, 'color' => 'green', 'requires_note' => false],
        closedLostStatus: ['name' => 'Persa', 'description' => null, 'color' => 'red', 'requires_note' => true],
    ));
}

function consultingSource(): QuoteWorkflow
{
    $consulenza = ProductCategory::factory()->create(['name' => 'Consulenza', 'parent_id' => null]);

    return cloneTestWorkflow(QualificaConsultingWorkflowCloneSeeder::SOURCE, WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD, $consulenza->id);
}

/**
 * @return list<array<string, mixed>>
 */
function workflowStatusShape(QuoteWorkflow $workflow): array
{
    return $workflow->statuses()->orderBy('sort_order')->get()
        ->map(fn (QuoteWorkflowStatus $status): array => [
            'name' => $status->name,
            'description' => $status->description,
            'color' => $status->color,
            'group' => $status->group->value,
            'system_key' => $status->system_key,
            'requires_note' => $status->requires_note,
        ])
        ->all();
}

function branchCriterionOf(QuoteWorkflow $workflow): array
{
    return $workflow->criteria()->get(['field', 'value_id'])->map(fn ($criterion): array => [$criterion->field, (int) $criterion->value_id])->all();
}

it('copies "Consulenza" on every uncovered new root, skipping Formazione and roots with their own workflow (AC-001, AC-002)', function (): void {
    $source = consultingSource();
    // A manual edit made from the configurator travels with the copy.
    $source->statuses()->where('name', 'In trattativa')->update(['name' => 'In trattativa (modificato)']);

    $iso = ProductCategory::factory()->create(['name' => 'ISO', 'parent_id' => null]);
    $gare = ProductCategory::factory()->create(['name' => 'Gare', 'parent_id' => null]);
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione', 'parent_id' => null]);
    $gol = ProductCategory::factory()->create(['name' => 'GOL - Molise', 'parent_id' => $formazione->id]);
    cloneTestWorkflow('GOL - Molise', WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD, $gol->id);
    $apl = ProductCategory::factory()->create(['name' => 'APL', 'parent_id' => null]);
    cloneTestWorkflow('APL', WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD, $apl->id);

    $this->seed(QualificaConsultingWorkflowCloneSeeder::class);

    $isoWorkflow = QuoteWorkflow::query()->where('name', 'ISO')->sole();

    expect(branchCriterionOf($isoWorkflow))->toBe([[WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD, $iso->id]])
        ->and(branchCriterionOf(QuoteWorkflow::query()->where('name', 'Gare')->sole()))->toBe([[WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD, $gare->id]])
        ->and($isoWorkflow->is_active)->toBeTrue()
        ->and(workflowStatusShape($isoWorkflow))->toBe(workflowStatusShape($source->fresh()))
        ->and(collect(workflowStatusShape($isoWorkflow))->pluck('name'))->toContain('In trattativa (modificato)')
        ->and(QuoteWorkflow::query()->where('name', 'Formazione')->exists())->toBeFalse()
        ->and(QuoteWorkflow::query()->where('name', 'APL')->count())->toBe(1)
        ->and(QuoteWorkflow::query()->count())->toBe(5);
});

it('leaves the legacy roots out and covers an inactive new root (AC-003)', function (): void {
    consultingSource();
    ProductCategory::factory()->create(['name' => '[OLD] ISO', 'parent_id' => null, 'old_id' => 1, 'is_active' => false]);
    ProductCategory::factory()->create(['name' => '[OLD] Costi', 'parent_id' => null, 'is_active' => false]);
    ProductCategory::factory()->create(['name' => 'No categoria', 'parent_id' => null, 'is_active' => false]);

    $this->seed(QualificaConsultingWorkflowCloneSeeder::class);

    expect(QuoteWorkflow::query()->where('name', 'like', '[OLD]%')->exists())->toBeFalse()
        ->and(QuoteWorkflow::query()->where('name', 'No categoria')->exists())->toBeTrue();
});

it('skips a root any workflow already points at, active or not, alone or with other criteria (AC-004)', function (): void {
    consultingSource();
    $soa = ProductCategory::factory()->create(['name' => 'SOA', 'parent_id' => null]);
    cloneTestWorkflow('SOA dedicato', WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD, $soa->id, isActive: false);

    $this->seed(QualificaConsultingWorkflowCloneSeeder::class);

    expect(QuoteWorkflow::query()->where('name', 'SOA')->exists())->toBeFalse()
        ->and(QuoteWorkflow::query()->count())->toBe(2);
});

it('creates nothing on a re-run, nothing without "Consulenza", and skips only a root whose name is taken (AC-005)', function (): void {
    ProductCategory::factory()->create(['name' => 'GDPR', 'parent_id' => null]);

    $this->seed(QualificaConsultingWorkflowCloneSeeder::class);
    expect(QuoteWorkflow::query()->count())->toBe(0);

    consultingSource();
    $sicurezza = ProductCategory::factory()->create(['name' => 'Sicurezza', 'parent_id' => null]);
    $sicurezzaChild = ProductCategory::factory()->create(['name' => 'Sicurezza - Documenti', 'parent_id' => $sicurezza->id]);
    // A workflow on another category already carries the root's name.
    cloneTestWorkflow('Sicurezza', WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD, $sicurezzaChild->id);

    $this->seed(QualificaConsultingWorkflowCloneSeeder::class);
    $this->seed(QualificaConsultingWorkflowCloneSeeder::class);

    expect(QuoteWorkflow::query()->where('name', 'GDPR')->count())->toBe(1)
        ->and(QuoteWorkflow::query()->where('name', 'Sicurezza')->sole()->criteria()->value('value_id'))->toBe($sicurezzaChild->id)
        ->and(QuoteWorkflow::query()->count())->toBe(3);
});
