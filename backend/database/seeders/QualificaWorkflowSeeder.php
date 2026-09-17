<?php

namespace Database\Seeders;

use App\DataObjects\QuoteWorkflows\CreateQuoteWorkflowData;
use App\DataObjects\QuoteWorkflows\UpdateQuoteWorkflowData;
use App\Enums\WorkflowStatusGroup;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Services\QuoteWorkflowService;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Illuminate\Database\Seeder;

/**
 * The client's "stati di lavorazione" (spec 0047): one QuoteWorkflow per
 * product category of WorkflowStatusCatalogue::WORKFLOWS, each matched on that
 * category — by EXACT category by default, by whole BRANCH for the categories
 * that declare it (WorkflowStatusCatalogue::criterionFieldFor, spec 0092) —
 * and carrying that category's own working-state pick list.
 *
 * Split out of QualificaCatalogSeeder — which calls it as its last step —
 * only because the two together would blow past the file-size limit; it is
 * part of the catalogue seed, not an independent dataset, and it MUST run
 * after QualificaCatalogSeeder::seedCatalog(): the criterion value is the
 * category's id, so the tree has to exist first.
 *
 * Every workflow is created through QuoteWorkflowService::create() — the
 * same path POST /api/quote-workflows uses — so the real write path runs
 * (signature uniqueness, criteria sync, the pinned system rows added by
 * WorkflowStatusWriter around the custom ones), never a raw insert.
 *
 * Those pinned rows are seeded with the sheet's OWN labels, not the writer's
 * generic "Aperta"/"Chiusa positiva"/"Chiusa negativa": each takes over the
 * first state its block classifies under the same group (user decision
 * 2026-07-28), so no label foreign to the sheet reaches the pick list.
 *
 * Idempotent: a category whose workflow already exists (by name OR by criteria
 * signature, both unique) is skipped, so a re-run neither duplicates nor
 * overwrites the manual edits made from the configurator. The one exception is
 * a criterion FIELD the catalogue changed since: see realignCriterionField().
 */
class QualificaWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(QuoteWorkflowService::class);

        foreach (array_keys(WorkflowStatusCatalogue::WORKFLOWS) as $categoryName) {
            // Created by QualificaCatalogSeeder::seedCatalog(): a miss means
            // the two lists drifted apart, which must fail loudly rather than
            // silently drop a whole region's statuses.
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

            $this->seedWorkflow($service, $category);
        }
    }

    /**
     * One workflow named after $category and matched on it alone. The
     * workflow name doubles as the natural key: it is unique, and so is the
     * single-criterion signature, so either one already taken means this set
     * is seeded.
     */
    private function seedWorkflow(QuoteWorkflowService $service, ProductCategory $category): void
    {
        $criteria = [[
            'field' => WorkflowStatusCatalogue::criterionFieldFor($category->name),
            'value_id' => $category->id,
        ]];

        $existing = QuoteWorkflow::query()->where('name', $category->name)->first();

        if ($existing !== null) {
            $this->realignCriterionField($service, $existing, $category, $criteria);

            return;
        }

        if (QuoteWorkflow::query()->where('criteria_signature', CreateQuoteWorkflowData::computeSignature($criteria))->exists()) {
            return;
        }

        // The pinned system rows carry the sheet's own labels rather than the
        // writer's generic ones (user decision 2026-07-28); the rows they take
        // over are dropped from the custom list, never seeded twice.
        $pinned = WorkflowStatusCatalogue::pinnedStatusesFor($category->name);

        $service->create(new CreateQuoteWorkflowData(
            name: $category->name,
            isActive: true,
            criteria: $criteria,
            statuses: WorkflowStatusCatalogue::customStatusesFor($category->name),
            openStatus: $pinned[WorkflowStatusGroup::Open->value],
            closedWonStatus: $pinned[WorkflowStatusGroup::ClosedWon->value],
            closedLostStatus: $pinned[WorkflowStatusGroup::ClosedLost->value],
        ));
    }

    /**
     * Moves an already-seeded workflow onto the criterion field the catalogue
     * declares today, when it still carries the one an earlier revision wrote:
     * the same category, the OTHER field. "DIL" is the case — matched on its
     * exact category until it became a container (user directive 2026-09-17),
     * so its offers on "DIL - Lombardia" would otherwise never reach its set.
     *
     * Any other criteria set is a configurator edit and is left alone.
     *
     * @param  list<array{field: string, value_id: int}>  $criteria
     */
    private function realignCriterionField(QuoteWorkflowService $service, QuoteWorkflow $workflow, ProductCategory $category, array $criteria): void
    {
        $staleField = $criteria[0]['field'] === WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD
            ? WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD
            : WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD;

        $staleSignature = CreateQuoteWorkflowData::computeSignature([['field' => $staleField, 'value_id' => $category->id]]);

        if ($workflow->criteria_signature !== $staleSignature) {
            return;
        }

        $service->update($workflow, new UpdateQuoteWorkflowData(criteria: $criteria));
    }
}
