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
    ) {}

    public function run(int $opportunities = self::DEFAULT_OPPORTUNITIES): void
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

        // Step 2: the batch itself — one deal per anagrafica (user directive
        // 2026-08-31: an anagrafica carries one open opportunity at a time), so
        // the batch is capped by how many registries exist.
        $count = min($opportunities, $registries->count());

        for ($index = 0; $index < $count; $index++) {
            $this->seedOpportunity($faker, $index, $registries[$index], $lookups);
        }

        $this->command?->info(sprintf('%d sample opportunities seeded with no lead behind them.', $count));
    }

    /**
     * @param  array{sources: Collection<int, Source>, sites: Collection<int, OperationalSite>, managers: Collection<int, User>}  $lookups
     */
    private function seedOpportunity(
        Generator $faker,
        int $index,
        Registry $registry,
        array $lookups,
    ): void {
        $offer = $this->pickOffer($faker, $index);
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
