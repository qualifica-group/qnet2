<?php

use App\Models\BusinessFunction;
use App\Models\Contract;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\QualificaSampleDataSeeder;
use Database\Seeders\QualificaSampleOpportunitySeeder;
use Database\Seeders\Support\SampleCategoryCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;

// User directive 2026-09-29: whatever the batch sizes, the sample chain
// leaves at least one Opportunita' and one Offerta on every SELLABLE product
// category, plus a Contratto and a Commessa where the branch is sold under a
// contract; and every Commessa is stamped from one of the loaded Modelli di
// Task.
uses(RefreshDatabase::class);

/** Sellable categories sold under a contract: more than the batch sizes below can reach. */
const COVERAGE_CONTRACT_CATEGORIES = 4;

/**
 * A catalogue wider than the batch: COVERAGE_CONTRACT_CATEGORIES sellable
 * branches sold under a contract, one sellable branch that is not
 * ("Formazione"), and two categories out of reach — one with no product, one
 * unselectable — plus two active templates and an inactive one.
 *
 * @return array{contract: list<int>, training: int, unreachable: list<int>, templates: list<int>, inactive: int}
 */
function seedCoverageCatalogue(): array
{
    $sellable = static function (array $rules = []): int {
        $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory(), ...$rules]);
        Product::factory()->create(['category_id' => $category->getKey()]);

        return $category->getKey();
    };

    $contract = [];
    for ($index = 0; $index < COVERAGE_CONTRACT_CATEGORIES; $index++) {
        $contract[] = $sellable();
    }

    $training = $sellable(['generates_contract' => false]);
    $withoutProduct = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()])->getKey();
    $unselectable = $sellable(['is_selectable' => false]);

    $templates = TaskTemplate::factory()->count(2)->create()->each(
        static fn (TaskTemplate $template) => TaskTemplateItem::factory()->forTemplate($template)->create(),
    );
    $inactive = TaskTemplate::factory()->inactive()->create();

    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    TaskType::factory()->create();

    return [
        'contract' => $contract,
        'training' => $training,
        'unreachable' => [$withoutProduct, $unselectable],
        'templates' => $templates->modelKeys(),
        'inactive' => $inactive->getKey(),
    ];
}

/**
 * @return list<int>
 */
function coveredCategoryIds(string $opportunityRelation): array
{
    return OpportunityProductLine::query()
        ->whereHas('opportunity', static fn ($query) => $query->has($opportunityRelation))
        ->distinct()
        ->pluck('product_category_id')
        ->all();
}

/** The smallest batch the chain accepts: far below the catalogue's width. */
function runSmallSampleChain(): void
{
    app(QualificaSampleDataSeeder::class)->setContainer(app())->run(
        leads: 3,
        convertedLeads: 1,
        opportunities: 1,
        requests: 1,
        quotes: 1,
        contracts: 1,
        workOrders: 1,
        tasks: 1,
        timeEntries: 1,
    );
}

it('covers every sellable category down to its deepest level, whatever the batch size', function (): void {
    $catalogue = seedCoverageCatalogue();

    runSmallSampleChain();

    $programmed = WorkOrder::query()->with('quote.opportunity.productLines')->get()
        ->flatMap(fn (WorkOrder $workOrder) => $workOrder->quote->opportunity->productLines->pluck('product_category_id'))
        ->unique()
        ->all();
    $sellable = [...$catalogue['contract'], $catalogue['training']];

    expect(coveredCategoryIds('quotes'))->toContain(...$sellable)
        ->and(coveredCategoryIds('quotes.contract'))->toContain(...$catalogue['contract'])
        ->and($programmed)->toContain(...$catalogue['contract'])
        // Spec 0091: a branch not sold under a contract never opens one.
        ->and(coveredCategoryIds('quotes.contract'))->not->toContain($catalogue['training'])
        // Out of reach by construction, never padded with a fabricated product.
        ->and(OpportunityProductLine::query()->whereIn('product_category_id', $catalogue['unreachable'])->exists())->toBeFalse()
        ->and(app(SampleCategoryCoverage::class)->incompleteCategoryIds())->toBe([]);
});

it('stamps every commessa from an active task template, each one used before any repeats', function (): void {
    $catalogue = seedCoverageCatalogue();

    runSmallSampleChain();

    $templateIds = WorkOrder::query()->pluck('task_template_id');

    expect(WorkOrder::query()->count())->toBeGreaterThanOrEqual(COVERAGE_CONTRACT_CATEGORIES)
        ->and($templateIds->filter(fn (?int $id): bool => $id === null))->toBeEmpty()
        ->and($templateIds->unique()->sort()->values()->all())->toBe($catalogue['templates'])
        ->and($templateIds)->not->toContain($catalogue['inactive'])
        // The template's rows became the commessa's tasks.
        ->and(Task::query()->whereNotNull('work_order_id')->distinct()->count('work_order_id'))->toBe(WorkOrder::query()->count());
});

it('adds nothing beyond the batch once every category is covered', function (): void {
    seedCoverageCatalogue();
    runSmallSampleChain();
    $opportunities = Opportunity::query()->count();
    $contracts = Contract::query()->count();

    runSmallSampleChain();

    // 1 converted + 1 lead-less + 1 request; 1 contract.
    expect(Opportunity::query()->count())->toBe($opportunities + 3)
        ->and(Contract::query()->count())->toBe($contracts + 1);
});

it('opens coverage deals only when the chain asks for them', function (): void {
    seedCoverageCatalogue();
    Registry::factory()->count(COVERAGE_CONTRACT_CATEGORIES + 3)->create();

    app(QualificaSampleOpportunitySeeder::class)->run(opportunities: 1);
    expect(Opportunity::query()->count())->toBe(1);

    // One per sellable category (COVERAGE_CONTRACT_CATEGORIES + the training
    // one) the running batch does not carry yet, then the batch of one.
    app(QualificaSampleOpportunitySeeder::class)->run(opportunities: 1, sinceOpportunityId: (int) Opportunity::query()->max('id'));
    expect(Opportunity::query()->count())->toBe(1 + COVERAGE_CONTRACT_CATEGORIES + 1 + 1);
});
