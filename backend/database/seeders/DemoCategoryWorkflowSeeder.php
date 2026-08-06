<?php

namespace Database\Seeders;

use App\DataObjects\QuoteWorkflows\CreateQuoteWorkflowData;
use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Services\QuoteWorkflowService;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use Database\Seeders\DemoCatalog\DemoWorkflowStatusCatalogue;
use Illuminate\Database\Seeder;

/**
 * The demo "stati di lavorazione" (spec 0047): one QuoteWorkflow per
 * category of the demo tree, matched on that category (criterion
 * `product_category_id`) and carrying its branch's own working-state pick
 * list — so a demo request can be walked from "da contattare" to a closure,
 * and the work panel shows a set that belongs to the offer instead of the
 * generic global default.
 *
 * ONE WORKFLOW PER CATEGORY, not one on the root: the criterion matches the
 * EXACT category of a product line (CriterionFieldRegistry walks no ancestor),
 * and the demo opportunities pair against roots and leaves alike.
 *
 * Created through QuoteWorkflowService::create() — the same path POST
 * /api/quote-workflows uses — so the real write path runs (signature
 * uniqueness, criteria sync, the 4 pinned system rows added by
 * WorkflowStatusWriter around the custom ones).
 *
 * MUST run after DemoQuoteWorkflowSeeder, which clears every workflow
 * before seeding its own source-matched ones, and before DemoOpportunitySeeder,
 * whose rows resolve their working-state at creation time.
 *
 * Idempotent: a category whose workflow already exists (by name OR by criteria
 * signature, both unique) is skipped, so a re-run neither duplicates nor
 * overwrites what was edited from the configurator.
 */
class DemoCategoryWorkflowSeeder extends Seeder
{
    public function __construct(private readonly QuoteWorkflowService $workflows) {}

    public function run(): void
    {
        foreach (DemoCategoryCatalogue::categoryNames() as $categoryName) {
            $category = ProductCategory::query()->where('name', $categoryName)->first();

            if ($category === null) {
                // Demo tree not seeded (partial run): nothing to match on.
                continue;
            }

            $this->seedWorkflow($category);
        }
    }

    private function seedWorkflow(ProductCategory $category): void
    {
        $criteria = [['field' => DemoWorkflowStatusCatalogue::CRITERION_FIELD, 'value_id' => $category->id]];

        $exists = QuoteWorkflow::query()
            ->where('name', $category->name)
            ->orWhere('criteria_signature', CreateQuoteWorkflowData::computeSignature($criteria))
            ->exists();

        if ($exists) {
            return;
        }

        $branch = DemoCategoryCatalogue::branchOf($category->name);
        $pinned = DemoWorkflowStatusCatalogue::PINNED[$branch];

        $this->workflows->create(new CreateQuoteWorkflowData(
            name: $category->name,
            isActive: true,
            criteria: $criteria,
            statuses: $this->customStatuses($branch),
            openStatus: $pinned[WorkflowStatusSystemKey::Open->value],
            validatedStatus: $pinned[WorkflowStatusSystemKey::Validated->value],
            closedWonStatus: $pinned[WorkflowStatusSystemKey::ClosedWon->value],
            closedLostStatus: $pinned[WorkflowStatusSystemKey::ClosedLost->value],
        ));
    }

    /**
     * The branch's intermediate rows, shaped to the DTO's custom-row contract
     * — the pinned ones are seeded through their own parameters, never listed
     * here.
     *
     * @return array<int, array{name: string, description: ?string, color: ?string, group: string, requires_note: bool}>
     */
    private function customStatuses(string $branch): array
    {
        return array_map(
            static fn (array $status): array => [
                'name' => $status['name'],
                'description' => $status['description'],
                'color' => $status['color'],
                'group' => WorkflowStatusGroup::from($status['group'])->value,
                'requires_note' => $status['requires_note'],
            ],
            DemoWorkflowStatusCatalogue::CUSTOM[$branch],
        );
    }
}
