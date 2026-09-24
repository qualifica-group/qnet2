<?php

namespace Database\Seeders;

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\Enums\CategoryManagementMode;
use App\Enums\ProductUsage;
use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Services\QuoteService;
use Database\Seeders\Concerns\ResolvesSeedActor;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The Offerte step of the sample dataset (user directive 2026-09-24): one
 * Offerta on each opportunity of THIS batch that has none yet — the converted
 * leads of step 1 and the standalone deals of step 2 (the requests of step 3
 * are born with their own).
 *
 * Every row goes through QuoteService::create(), the path POST /api/quotes
 * uses, so the code, the aggregates, the commissions and the resolved working
 * status (spec 0083) all come from the real write path. What the seeder
 * decides, it decides by the system's own rules:
 *
 *  - only opportunities with NO quote are picked, so the "single offer per
 *    opportunity" branches (OpportunityQuoteLimit) are satisfied by
 *    construction;
 *  - the REVENUE rows are products filed on a category the opportunity
 *    already carries and usable as SALE (spec 0142), so coverage never has to
 *    append a line; a `single` branch (spec 0077) gets exactly one row, with
 *    quantity one — a training course is sold once;
 *  - the COST row, when the catalogue has a product usable as COST, is
 *    imputed to the offer row on every other quote (spec 0144);
 *  - GA1 + GA2 "Operatore" are two distinct users promoted onto the
 *    opportunity (spec 0087), so the offer is visible in Gestione Richieste
 *    to someone other than a privileged account.
 *
 * The working status is then spread over the offer's OWN resolved set: some
 * stay on its `open` row, some move to a non-system open/pending row, some
 * close negatively. Closing positively is left to
 * QualificaSampleContractSeeder, the step that owns what that close triggers.
 *
 * `$sinceOpportunityId` confines the step to the rows the running chain has
 * just created (QualificaSampleDataSeeder passes its watermark), so real
 * opportunities on the same database are never touched. The Faker generator
 * is UNSEEDED, like its siblings: the chain accumulates.
 */
class QualificaSampleQuoteSeeder extends Seeder
{
    use ResolvesSeedActor;

    /** The batch size when the caller names none (`--quotes` of qualifica:seed-sample). */
    public const int DEFAULT_QUOTES = 15;

    private const int MAX_OFFER_ROWS = 2;

    private const int MAX_OFFER_QUANTITY = 5;

    private const int MAX_COST_QUANTITY = 3;

    private const string OUTCOME_BASELINE = 'baseline';

    private const string OUTCOME_WORKING = 'working';

    private const string OUTCOME_LOST = 'lost';

    /** Rotated over the seeded offers, one outcome each. */
    private const array OUTCOMES = [
        self::OUTCOME_BASELINE,
        self::OUTCOME_WORKING,
        self::OUTCOME_WORKING,
        self::OUTCOME_LOST,
    ];

    /** Mandatory note when the destination row `requires_note` (spec 0083, AC-023). */
    private const string STATUS_CHANGE_NOTE = 'Esito registrato dal seed di esempio.';

    public function __construct(
        private readonly QuoteService $quotes,
        private readonly QuoteWorkflowResolver $workflowResolver,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function run(int $quotes = self::DEFAULT_QUOTES, int $sinceOpportunityId = 0): void
    {
        // Step 1: the actor QuoteService writes on behalf of, and the batch's
        // opportunities still without an offer.
        $actor = $this->resolveActor();

        $opportunities = Opportunity::query()
            ->where('id', '>', $sinceOpportunityId)
            ->doesntHave('quotes')
            ->with(['productLines', 'registry'])
            ->orderBy('id')
            ->limit($quotes)
            ->get();

        if ($actor === null || $opportunities->isEmpty()) {
            $this->command?->warn('Sample quotes skipped: no user, or no opportunity without an offer.');

            return;
        }

        // Step 2: the catalogue, loaded once for the whole batch.
        $saleProducts = $this->productsByCategory(ProductUsage::Sale);
        $costProducts = Product::query()->whereJsonContains('usages', ProductUsage::Cost->value)->orderBy('id')->get();
        $modes = $this->hierarchy->rootManagementModesFor($saleProducts->keys()->all());
        $users = User::query()->orderBy('id')->get();
        $faker = FakerFactory::create('it_IT');

        // Step 3: the batch itself; an opportunity whose categories hold no
        // sellable product is skipped — an offer with no row could not close.
        $seeded = 0;

        foreach ($opportunities as $opportunity) {
            $offerLines = $this->offerLines($faker, $opportunity, $saleProducts, $modes);

            if ($offerLines === []) {
                continue;
            }

            $quote = $this->quotes->create(
                $this->buildQuoteData($faker, $seeded, $opportunity, $offerLines, $costProducts, $users),
                $actor,
            );
            $this->applyOutcome(self::OUTCOMES[$seeded % count(self::OUTCOMES)], $quote, $faker, $actor);
            $seeded++;
        }

        $this->command?->info(sprintf('%d sample quotes seeded.', $seeded));
    }

    /**
     * @param  array<int, QuoteLineData>  $offerLines
     * @param  Collection<int, Product>  $costProducts
     * @param  Collection<int, User>  $users
     */
    private function buildQuoteData(
        Generator $faker,
        int $index,
        Opportunity $opportunity,
        array $offerLines,
        Collection $costProducts,
        Collection $users,
    ): CreateQuoteData {
        return new CreateQuoteData(
            code: null,
            title: sprintf('Offerta %s', $opportunity->registry?->name ?? $opportunity->id),
            opportunityId: $opportunity->id,
            // Resolved by the service on the set applicable to THIS offer
            // (spec 0083, AC-020); the outcome is applied afterwards.
            workflowStatusId: null,
            note: null,
            commercialId: null,
            commercialIdSubmitted: false,
            reporterId: null,
            reporterIdSubmitted: false,
            supervisorId: null,
            supervisorIdSubmitted: false,
            internalNotes: $faker->boolean(40) ? $faker->sentence(10) : null,
            offerLines: $offerLines,
            costLines: $costProducts->isEmpty() ? [] : [$this->costLine($faker, $costProducts, $index)],
            managerSlots: $this->managerSlots($users, $index),
            promoteManagersToOpportunity: true,
        );
    }

    /**
     * REVENUE rows on the products of the opportunity's own categories: one
     * row with quantity one on a `single` branch, up to MAX_OFFER_ROWS
     * distinct products otherwise.
     *
     * @param  Collection<int, Collection<int, Product>>  $saleProducts
     * @param  array<int, array{root_id: int, management_mode: CategoryManagementMode}|null>  $modes
     * @return array<int, QuoteLineData>
     */
    private function offerLines(Generator $faker, Opportunity $opportunity, Collection $saleProducts, array $modes): array
    {
        $categoryIds = $opportunity->productLines->pluck('product_category_id')->all();
        $candidates = $saleProducts->only($categoryIds)->flatten(1);

        if ($candidates->isEmpty()) {
            return [];
        }

        $isSingle = array_any(
            $categoryIds,
            static fn (int $id): bool => ($modes[$id]['management_mode'] ?? null) === CategoryManagementMode::Single,
        );
        $rowCount = $isSingle ? 1 : min($faker->numberBetween(1, self::MAX_OFFER_ROWS), $candidates->count());

        return $candidates->random($rowCount)
            ->map(fn (Product $product): QuoteLineData => new QuoteLineData(
                productId: $product->id,
                quantity: $isSingle ? 1.0 : (float) $faker->numberBetween(1, self::MAX_OFFER_QUANTITY),
                unitPrice: (float) $product->price,
                vatRateId: $product->vat_rate_id,
                sortOrder: null,
            ))
            ->values()
            ->all();
    }

    /**
     * A COST row priced at the product's `cost` (spec 0065, D-6), imputed to
     * the first offer row on every other quote (spec 0144, D-2).
     *
     * @param  Collection<int, Product>  $costProducts
     */
    private function costLine(Generator $faker, Collection $costProducts, int $index): QuoteLineData
    {
        $product = $costProducts[$index % $costProducts->count()];

        return new QuoteLineData(
            productId: $product->id,
            quantity: (float) $faker->numberBetween(1, self::MAX_COST_QUANTITY),
            unitPrice: (float) $product->cost,
            vatRateId: $product->vat_rate_id,
            sortOrder: null,
            offerLineIndex: $index % 2 === 0 ? 0 : null,
        );
    }

    /**
     * GA1 + GA2 "Operatore", two distinct users rotated by $index; null (the
     * service then inherits the opportunity's own) with fewer than two.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, int>|null
     */
    private function managerSlots(Collection $users, int $index): ?array
    {
        if ($users->count() < 2) {
            return null;
        }

        return [
            $users[$index % $users->count()]->id,
            $users[($index + 1) % $users->count()]->id,
        ];
    }

    /**
     * Moves the offer onto a row of ITS OWN resolved set (spec 0083, AC-021),
     * through QuoteService::update() — the path PATCH /api/quotes uses.
     */
    private function applyOutcome(string $outcome, Quote $quote, Generator $faker, User $actor): void
    {
        if ($outcome === self::OUTCOME_BASELINE) {
            return;
        }

        $statuses = $this->workflowResolver->statusesFor($this->workflowResolver->resolve($quote));

        $target = $outcome === self::OUTCOME_LOST
            ? $statuses->firstWhere('system_key', WorkflowStatusSystemKey::ClosedLost->value)
            : $this->workingStatus($faker, $statuses);

        if ($target === null) {
            return;
        }

        $this->quotes->update(
            $quote,
            new UpdateQuoteData(
                workflowStatusId: $target->id,
                workflowStatusIdSubmitted: true,
                note: $target->requires_note ? self::STATUS_CHANGE_NOTE : null,
            ),
            $actor,
        );
    }

    /**
     * A custom (non-system) row of the open/pending groups — "Da richiamare",
     * "In trattativa"... — or null when the set has none.
     *
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     */
    private function workingStatus(Generator $faker, Collection $statuses): ?QuoteWorkflowStatus
    {
        $working = $statuses
            ->filter(static fn (QuoteWorkflowStatus $status): bool => $status->system_key === null
                && in_array($status->group, [WorkflowStatusGroup::Open, WorkflowStatusGroup::Pending], true))
            ->values();

        return $working->isEmpty() ? null : $faker->randomElement($working->all());
    }

    /**
     * category id => the products filed on it and usable as $usage.
     *
     * @return Collection<int, Collection<int, Product>>
     */
    private function productsByCategory(ProductUsage $usage): Collection
    {
        return Product::query()
            ->whereJsonContains('usages', $usage->value)
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->get()
            // Base collection: an Eloquent one's only() filters by model key.
            ->toBase()
            ->groupBy('category_id');
    }
}
