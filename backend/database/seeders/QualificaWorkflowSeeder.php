<?php

namespace Database\Seeders;

use App\DataObjects\QuoteWorkflows\CreateQuoteWorkflowData;
use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Services\QuoteWorkflowService;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Illuminate\Database\Seeder;

/**
 * The client's "stati di lavorazione" (spec 0047): one QuoteWorkflow per
 * product category of WorkflowStatusCatalogue::WORKFLOWS, each matched on that
 * category (criterion `product_category_id`) and carrying that category's own
 * working-state pick list.
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
 * 2026-07-28), so no label foreign to the sheet reaches the pick list. The
 * optional 'validated' row is the exception — promoted by NAME, and only for
 * the one state the sheet has for it, "OK_Da Caricare" (user directive
 * 2026-08-03): every other workflow is seeded without it.
 *
 * Idempotent: a category whose workflow already exists (by name OR by criteria
 * signature, both unique) is skipped, so a re-run neither duplicates nor
 * overwrites the manual edits made from the configurator.
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
        $criteria = [['field' => WorkflowStatusCatalogue::CRITERION_FIELD, 'value_id' => $category->id]];

        $exists = QuoteWorkflow::query()
            ->where('name', $category->name)
            ->orWhere('criteria_signature', CreateQuoteWorkflowData::computeSignature($criteria))
            ->exists();

        if ($exists) {
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
            // Null for every section but AUTOIMPIEGO/YISU, whose
            // "OK_Da Caricare" is the only state carrying the optional
            // 'validated' row: those sets get none.
            validatedStatus: $pinned[WorkflowStatusSystemKey::Validated->value],
            closedWonStatus: $pinned[WorkflowStatusGroup::ClosedWon->value],
            closedLostStatus: $pinned[WorkflowStatusGroup::ClosedLost->value],
        ));
    }
}
