<?php

namespace Database\Seeders;

use App\DataObjects\QuoteWorkflows\CreateQuoteWorkflowData;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowCriterion;
use App\Models\QuoteWorkflowStatus;
use App\Services\QuoteWorkflowService;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Gives every new root category without a "stati offerta" workflow of its own
 * a copy of the "Consulenza" workflow, as it stands in the database (spec
 * 0354, user directive 2026-11-05):
 * `php artisan db:seed --class=QualificaConsultingWorkflowCloneSeeder`.
 * QualificaProductionDataSeeder runs it once the whole catalogue exists.
 *
 * A root is covered when any workflow, active or not, has a criterion on it;
 * the "Formazione" branch has its own workflows on the sub-categories and is
 * left out, and so is the legacy catalogue (`old_id`, "[OLD] " name).
 *
 * Each copy is created through QuoteWorkflowService::create(), named after its
 * root and matched on the root's whole branch. Re-running creates nothing: a
 * copied root now carries a criterion.
 */
class QualificaConsultingWorkflowCloneSeeder extends Seeder
{
    /** The workflow copied, as QualificaWorkflowSeeder names it. */
    public const string SOURCE = 'Consulenza';

    /** Roots whose offers have their own workflows below the root. */
    public const array EXCLUDED_ROOTS = ['Formazione'];

    private const string LEGACY_PREFIX = '[OLD] ';

    /** The criterion fields that point a workflow at a category. */
    private const array CATEGORY_FIELDS = [
        WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD,
        WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD,
    ];

    public function __construct(private readonly QuoteWorkflowService $service) {}

    public function run(): void
    {
        // Step 1: the workflow to copy, with its statuses in order.
        $source = QuoteWorkflow::query()->where('name', self::SOURCE)->first();

        if ($source === null) {
            $this->command?->warn(sprintf('Workflow "%s" not found: no copy made.', self::SOURCE));

            return;
        }

        $statuses = $source->statuses()->orderBy('sort_order')->get();

        // Step 2: one copy per uncovered root.
        $created = [];

        foreach ($this->uncoveredRoots() as $root) {
            if (QuoteWorkflow::query()->where('name', $root->name)->exists()) {
                $this->command?->warn(sprintf('Workflow name "%s" already used: root skipped.', $root->name));

                continue;
            }

            $this->service->create($this->copyFor($source, $statuses, $root));
            $created[] = $root->name;
        }

        $this->command?->info($created === []
            ? 'No root to cover.'
            : sprintf('%d workflows copied from %s: %s.', count($created), self::SOURCE, implode(', ', $created)));
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    private function uncoveredRoots(): Collection
    {
        $coveredIds = QuoteWorkflowCriterion::query()
            ->whereIn('field', self::CATEGORY_FIELDS)
            ->select('value_id');

        return ProductCategory::query()
            ->whereNull('parent_id')
            ->whereNull('old_id')
            ->where('name', 'not like', self::LEGACY_PREFIX.'%')
            ->whereNotIn('name', self::EXCLUDED_ROOTS)
            ->whereNotIn('id', $coveredIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     */
    private function copyFor(QuoteWorkflow $source, Collection $statuses, ProductCategory $root): CreateQuoteWorkflowData
    {
        $system = $statuses->filter(fn (QuoteWorkflowStatus $status): bool => $status->isSystem())->keyBy('system_key');

        return new CreateQuoteWorkflowData(
            name: $root->name,
            isActive: $source->is_active,
            criteria: [['field' => WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD, 'value_id' => $root->id]],
            statuses: $statuses
                ->reject(fn (QuoteWorkflowStatus $status): bool => $status->isSystem())
                ->map(fn (QuoteWorkflowStatus $status): array => [
                    ...$this->descriptive($status),
                    'group' => $status->group->value,
                ])
                ->values()
                ->all(),
            openStatus: $this->systemRow($system, WorkflowStatusSystemKey::Open),
            closedWonStatus: $this->systemRow($system, WorkflowStatusSystemKey::ClosedWon),
            closedLostStatus: $this->systemRow($system, WorkflowStatusSystemKey::ClosedLost),
        );
    }

    /**
     * @param  Collection<string, QuoteWorkflowStatus>  $system
     * @return array{name: string, description: ?string, color: ?string, requires_note: bool}|null
     */
    private function systemRow(Collection $system, WorkflowStatusSystemKey $key): ?array
    {
        $status = $system->get($key->value);

        return $status === null ? null : $this->descriptive($status);
    }

    /**
     * @return array{name: string, description: ?string, color: ?string, requires_note: bool}
     */
    private function descriptive(QuoteWorkflowStatus $status): array
    {
        return [
            'name' => $status->name,
            'description' => $status->description,
            'color' => $status->color,
            'requires_note' => $status->requires_note,
        ];
    }
}
