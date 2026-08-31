<?php

namespace Database\Seeders;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\Opportunities\RegistryOpenOpportunityGuard;
use App\Services\OpportunityService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\PicksDemoOffers;
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
 * Like its sibling this is fabricated data, not client reference data; it is
 * the LAST step of QualificaProductionDataSeeder because it reuses the
 * Anagrafiche that step seeds, rather than creating a second set of its own.
 *
 * Every row satisfies the opportunity form's two mandatory collections
 * (`product_lines` and `products_of_interest`, both min:1 in
 * StoreOpportunityRequest): PicksDemoOffers draws them together, so a line's
 * category always owns the products picked with it and the seeded rows are
 * re-submittable from the edit form. The trait is shared with
 * DemoOpportunitySeeder on purpose — the draw is the same one, and a second
 * copy would drift.
 *
 * Idempotent by presence: a database that already holds a lead-less
 * opportunity short-circuits the run, so re-seeding neither duplicates the
 * batch nor deletes deals created on top of it.
 */
class QualificaSampleOpportunitySeeder extends Seeder
{
    use PicksDemoOffers;

    private const int OPPORTUNITIES = 10;

    private const int FAKER_SEED = 20260731;

    public function __construct(
        private readonly OpportunityService $opportunities,
        private readonly CategoryHierarchy $hierarchy,
        private readonly RegistryOpenOpportunityGuard $openOpportunityGuard,
    ) {}

    public function run(): void
    {
        // Step 1: nothing to add once a standalone deal exists.
        if (Opportunity::query()->whereNull('lead_id')->exists()) {
            $this->command?->info('Sample opportunities already seeded: nothing to add.');

            return;
        }

        // User directive 2026-08-31: an anagrafica carries ONE open
        // opportunity at a time, and the lead step right before this one has
        // already converted some of its own registries — those are off limits.
        $registries = $this->freeRegistries();
        $this->loadOffers($this->hierarchy);

        // Step 2: without the mandatory Anagrafica (spec 0040, D-4) or an
        // offer to fill the two mandatory collections with, a seeded row
        // would be one the form itself refuses to submit.
        if ($registries->isEmpty() || ! $this->hasOffers()) {
            $this->command?->warn('Sample opportunities skipped: no registry, or no product category pairing a business function with a product.');

            return;
        }

        $faker = FakerFactory::create('it_IT');
        $faker->seed(self::FAKER_SEED);

        $lookups = [
            'sources' => Source::query()->orderBy('id')->get(),
            'sites' => OperationalSite::query()->orderBy('id')->get(),
            'managers' => User::query()->orderBy('id')->get(),
        ];

        // Step 3: the batch itself — one deal per anagrafica (user directive
        // 2026-08-31: an anagrafica carries one open opportunity at a time), so
        // the batch is capped by how many registries exist.
        $count = min(self::OPPORTUNITIES, $registries->count());

        for ($index = 0; $index < $count; $index++) {
            $this->seedOpportunity($faker, $index, $registries[$index], $lookups);
        }

        $this->command?->info(sprintf('%d sample opportunities seeded with no lead behind them.', $count));
    }

    /**
     * The anagrafiche with no open opportunity yet, in id order — the only
     * ones a new deal may hang on.
     *
     * @return Collection<int, Registry>
     */
    private function freeRegistries(): Collection
    {
        /** @var Collection<int, Registry> $registries */
        $registries = Registry::query()->orderBy('id')->get();

        $busy = $this->openOpportunityGuard->openOpportunityIdsByRegistry($registries->modelKeys());

        return $registries
            ->reject(static fn (Registry $registry): bool => isset($busy[$registry->getKey()]))
            ->values();
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
