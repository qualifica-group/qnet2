<?php

namespace Database\Seeders;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\Models\OperationalSite;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\Opportunities\RegistryOpenOpportunityGuard;
use App\Services\OpportunityService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\PicksDemoOffers;
use Database\Seeders\Concerns\PicksFreeRegistries;
use Database\Seeders\Support\SampleCategoryCoverage;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The STANDALONE half of the sample commercial pipeline (user directive
 * 2026-07-31): opportunities with no lead behind them — the "trattativa
 * diretta" creation path — as opposed to the converted ones
 * QualificaSampleLeadSeeder produces through spec 0044.
 *
 * Like its siblings this is fabricated data, not client reference data; it is
 * the second step of QualificaSampleDataSeeder because it reuses the
 * Anagrafiche step 1 seeds, rather than creating a second set of its own.
 *
 * Every row satisfies the opportunity form's two mandatory collections
 * (`product_lines` and `products_of_interest`, both min:1 in
 * StoreOpportunityRequest): PicksDemoOffers draws them together, so a line's
 * category always owns the products picked with it and the seeded rows are
 * re-submittable from the edit form. The trait is shared with
 * DemoOpportunitySeeder on purpose — the draw is the same one, and a second
 * copy would drift.
 *
 * COVERAGE FIRST (user directive 2026-09-29): before the batch, one deal for
 * every sellable category not yet carried down to its deepest level
 * (SampleCategoryCoverage) and not already on a deal of the running batch —
 * the later steps then take those deals through Offerta, Contratto and
 * Commessa. It is on top of `$opportunities`, and it takes its Anagrafiche
 * from the same free pool: QualificaSampleDataSeeder appends one lead per
 * category to cover, so the pool is sized for it. Only the chain asks for it
 * (by passing its watermark): run on its own, no later step would take those
 * deals any further, and every run would open them again.
 *
 * ACCUMULATES, it does not converge (user directive 2026-09-08): every run
 * appends deals on whatever Anagrafiche are still free, so the seeder can be
 * launched again whenever more rows are wanted. The batch size is a `run()`
 * parameter — `php artisan qualifica:seed-sample --opportunities=50` — so one
 * run can also be made bigger instead of repeated. What caps a run is the pool
 * itself, never a guard: with no free anagrafica left the batch is empty and
 * the seeder says so. The Faker generator is deliberately UNSEEDED for the
 * same reason — a fixed seed would make every run a carbon copy of the first.
 */
class QualificaSampleOpportunitySeeder extends Seeder
{
    use PicksDemoOffers;
    use PicksFreeRegistries;

    /** The batch size when the caller names none (`--opportunities` of qualifica:seed-sample). */
    public const int DEFAULT_OPPORTUNITIES = 10;

    public function __construct(
        private readonly OpportunityService $opportunities,
        private readonly CategoryHierarchy $hierarchy,
        private readonly RegistryOpenOpportunityGuard $openOpportunityGuard,
        private readonly SampleCategoryCoverage $coverage,
    ) {}

    /**
     * $sinceOpportunityId is the running chain's watermark: a category a deal
     * of the batch already carries needs no coverage deal. Null (run on its
     * own) seeds no coverage deal at all.
     */
    public function run(int $opportunities = self::DEFAULT_OPPORTUNITIES, ?int $sinceOpportunityId = null): void
    {

        // User directive 2026-08-31: an anagrafica carries ONE open
        // opportunity at a time, and the lead step right before this one has
        // already converted some of its own registries — those are off limits.
        $registries = $this->freeRegistries($this->openOpportunityGuard);
        $this->loadOffers($this->hierarchy);

        // Step 1: without the mandatory Anagrafica (spec 0040, D-4) or an
        // offer to fill the two mandatory collections with, a seeded row
        // would be one the form itself refuses to submit.
        if ($registries->isEmpty() || ! $this->hasOffers()) {
            $this->command?->warn('Sample opportunities skipped: no registry, or no product category pairing a business function with a product.');

            return;
        }

        $faker = FakerFactory::create('it_IT');

        $lookups = [
            'sources' => Source::query()->orderBy('id')->get(),
            'sites' => OperationalSite::query()->orderBy('id')->get(),
            'managers' => User::query()->orderBy('id')->get(),
        ];

        // Step 2: one deal per category still to cover, each on its own
        // anagrafica (user directive 2026-08-31: one open opportunity at a time).
        $covering = $sinceOpportunityId === null ? 0 : $this->seedCoverage($faker, $registries, $lookups, $sinceOpportunityId);

        // Step 3: the batch itself, on the anagrafiche step 2 left — so it is
        // capped by how many registries exist.
        $count = min($opportunities, $registries->count() - $covering);

        for ($index = 0; $index < $count; $index++) {
            $slot = $covering + $index;
            $this->seedOpportunity($faker, $slot, $registries[$slot], $lookups, $this->pickOffer($faker, $index));
        }

        $this->command?->info(sprintf(
            '%d sample opportunities seeded with no lead behind them, %d of them to cover a category.',
            $covering + $count,
            $covering,
        ));
    }

    /**
     * @param  Collection<int, Registry>  $registries
     * @param  array{sources: Collection<int, Source>, sites: Collection<int, OperationalSite>, managers: Collection<int, User>}  $lookups
     * @return int how many registries the coverage deals took, from the head of $registries
     */
    private function seedCoverage(Generator $faker, Collection $registries, array $lookups, int $sinceOpportunityId): int
    {
        $categoryIds = array_values(array_diff(
            $this->coverage->incompleteCategoryIds(),
            $this->coverage->categoryIdsCarriedSince($sinceOpportunityId),
        ));
        $seeded = 0;

        foreach ($categoryIds as $categoryId) {
            if ($seeded === $registries->count()) {
                $this->command?->warn(sprintf('%d categories left uncovered: no free registry left.', count($categoryIds) - $seeded));

                break;
            }

            $offer = $this->pickOfferFor($faker, $seeded, $categoryId);

            if ($offer !== null) {
                $this->seedOpportunity($faker, $seeded, $registries[$seeded], $lookups, $offer);
                $seeded++;
            }
        }

        return $seeded;
    }

    /**
     * @param  array{sources: Collection<int, Source>, sites: Collection<int, OperationalSite>, managers: Collection<int, User>}  $lookups
     * @param  array{product_lines: list<array{business_function_id: int, product_category_id: int}>, products_of_interest: list<int>}  $offer
     */
    private function seedOpportunity(
        Generator $faker,
        int $index,
        Registry $registry,
        array $lookups,
        array $offer,
    ): void {
        $startDate = $faker->dateTimeBetween('-6 months', 'now');

        $this->opportunities->create(new CreateOpportunityData(
            registryId: $registry->id,
            referentId: null,
            commercialId: null,
            reporterId: null,
            supervisorId: $this->pick($lookups['managers'], $index)?->id,
            sourceId: $this->pick($lookups['sources'], $index)?->id,
            // The whole point of this batch: no lead behind the deal, so
            // nothing is BR-1-derived and nothing is locked.
            leadId: null,
            managerSlots: $this->managerSlots($lookups['managers'], $index),
            productLines: $offer['product_lines'],
            startDate: $startDate->format('Y-m-d'),
            estimatedValue: $faker->randomFloat(2, 1500, 90000),
            expectedCloseDate: (clone $startDate)->modify('+'.$faker->numberBetween(1, 6).' months')->format('Y-m-d'),
            successProbability: $faker->numberBetween(10, 90),
            productsOfInterest: $offer['products_of_interest'],
            operationalSiteId: $this->pick($lookups['sites'], $index)?->id,
            generalNotes: $faker->boolean(60) ? $faker->sentence(12) : null,
        ));
    }

    /**
     * A single "G.A. 1" slot: array order IS the pivot `position`, so one
     * entry means one manager in the first slot.
     *
     * @param  Collection<int, User>  $managers
     * @return array<int, int>|null
     */
    private function managerSlots(Collection $managers, int $index): ?array
    {
        $manager = $this->pick($managers, $index + 1);

        return $manager === null ? null : [$manager->id];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $items
     * @return TModel|null
     */
    private function pick(Collection $items, int $index): mixed
    {
        return $items->isNotEmpty() ? $items[$index % $items->count()] : null;
    }
}
