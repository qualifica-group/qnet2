<?php

namespace Database\Seeders;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\RewardType;
use App\Models\Source;
use App\Models\User;
use App\Services\OpportunityService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\PicksDemoOffers;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the opportunities module (spec 0040): round-robins
 * existing registries against the optional lookups for a batch of STANDALONE
 * deals, then generates a handful more FROM existing leads (BR-1) so the demo
 * grid exercises both creation paths and the `locked_fields`/`lead` detail
 * shape.
 *
 * Every opportunity is created through OpportunityService::create() — the
 * same path POST /api/opportunities uses — so this exercises the real write
 * path (including the BR-1 derivation), not a raw insert. Idempotent:
 * existing opportunities are cleared first (harmless — nothing else
 * references an Opportunity, restrictOnDelete only runs the OTHER way).
 *
 * EVERY SEEDED ROW SATISFIES THE FORM'S MANDATORY FIELDS (StoreOpportunityRequest
 * / opportunity-schema.ts): `registry_id`, `product_lines` (min:1) and
 * `products_of_interest` (min:1). The seeder used
 * to leave the last two empty most of the time, which produced demo rows the
 * edit form itself refused to submit — they are drawn together by
 * PicksDemoOffers so a line's category always owns the products picked with it.
 *
 * Depends on DemoRegistrySeeder (mandatory relation), DemoProductCategorySeeder
 * + DemoProductSeeder (the mandatory collections above), plus
 * DemoBusinessFunctionSeeder/
 * DemoReferentSeeder/DemoUsersSeeder/DemoSourceSeeder/DemoOperationalSiteSeeder/
 * DemoRewardTypeSeeder/DemoLeadSeeder for the optional lookups. A no-op when
 * registries or offers are missing: without them no VALID opportunity can be
 * built, and a half-filled one is worse than none.
 */
class DemoOpportunitySeeder extends Seeder
{
    use PicksDemoOffers;

    private const int STANDALONE_OPPORTUNITIES = 30;

    private const int FROM_LEAD_OPPORTUNITIES = 15;

    private const int MAX_MANAGER_SLOTS = 3;

    public function __construct(
        private readonly OpportunityService $opportunities,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function run(): void
    {
        Opportunity::query()->delete();

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260716);

        $registries = Registry::query()->orderBy('id')->get();
        $this->loadOffers($this->hierarchy);

        if ($registries->isEmpty() || ! $this->hasOffers()) {
            // Nothing VALID to seed without the mandatory relation (D-4) or
            // without a category that pairs a business function with a real
            // product (`product_lines`/`products_of_interest`, both min:1).
            return;
        }

        $lookups = [
            'referents' => Referent::query()->orderBy('id')->get(),
            'supervisors' => User::query()->orderBy('id')->get(),
            'sources' => Source::query()->orderBy('id')->get(),
            'sites' => OperationalSite::query()->orderBy('id')->get(),
        ];

        $rewardTypeIds = RewardType::query()->orderBy('id')->pluck('id')->all();

        for ($index = 0; $index < self::STANDALONE_OPPORTUNITIES; $index++) {
            $this->createStandalone($faker, $index, $registries, $lookups, $rewardTypeIds);
        }

        $this->createFromLeads($faker, $lookups);
    }

    /**
     * @param  Collection<int, Registry>  $registries
     * @param  array{referents: Collection<int, Referent>, supervisors: Collection<int, User>, sources: Collection<int, Source>, sites: Collection<int, OperationalSite>}  $lookups
     * @param  array<int, int>  $rewardTypeIds
     */
    private function createStandalone(
        Generator $faker,
        int $index,
        Collection $registries,
        array $lookups,
        array $rewardTypeIds,
    ): void {
        $offer = $this->pickOffer($faker, $index);
        $reporterId = $this->maybePick($lookups['referents'], $index + 6, $faker, 30)?->id;

        $data = new CreateOpportunityData(
            registryId: $registries[$index % $registries->count()]->id,
            referentId: $this->maybePick($lookups['referents'], $index + 4, $faker, 60)?->id,
            commercialId: $this->maybePick($lookups['referents'], $index + 5, $faker, 40)?->id,
            reporterId: $reporterId,
            supervisorId: $this->maybePick($lookups['supervisors'], $index + 7, $faker, 60)?->id,
            sourceId: $this->maybePick($lookups['sources'], $index + 8, $faker, 50)?->id,
            leadId: null,
            managerSlots: $this->maybeManagerSlots($lookups['supervisors'], $faker),
            productLines: $offer['product_lines'],
            startDate: $faker->optional()->date(),
            estimatedValue: $faker->optional()->randomFloat(2, 1000, 200000),
            expectedCloseDate: $faker->optional()->date(),
            successProbability: $faker->numberBetween(0, 100),
            productsOfInterest: $offer['products_of_interest'],
            // The site is a plain optional FK (spec 0056), never derived.
            operationalSiteId: $this->maybePick($lookups['sites'], $index + 9, $faker, 40)?->id,
            // spec 0059, D-3: the beneficiary is the Segnalatore, so a reward
            // without a reporter has nobody to belong to.
            rewards: $this->maybeRewards($rewardTypeIds, $reporterId, $index, $faker),
            generalNotes: $faker->optional(0.6)->sentence(12),
        );

        $this->opportunities->create($data);
    }

    /**
     * A handful of opportunities generated FROM existing leads that do not
     * already have one (BR-1) — exercises the derivation path and the
     * `lead`/`locked_fields` detail shape the standalone batch above never
     * touches. Registry/source come from the lead; the two mandatory
     * collections do NOT derive from it and are drawn here like everywhere else.
     *
     * @param  array{referents: Collection<int, Referent>, supervisors: Collection<int, User>, sources: Collection<int, Source>, sites: Collection<int, OperationalSite>}  $lookups
     */
    private function createFromLeads(Generator $faker, array $lookups): void
    {
        $leads = Lead::query()->doesntHave('opportunity')->orderBy('id')->limit(self::FROM_LEAD_OPPORTUNITIES)->get();

        foreach ($leads as $index => $lead) {
            $offer = $this->pickOffer($faker, $index + self::STANDALONE_OPPORTUNITIES);

            $data = new CreateOpportunityData(
                registryId: null,
                referentId: null,
                commercialId: null,
                reporterId: null,
                supervisorId: $this->maybePick($lookups['supervisors'], $index, $faker, 50)?->id,
                sourceId: null,
                leadId: $lead->id,
                managerSlots: $this->maybeManagerSlots($lookups['supervisors'], $faker),
                productLines: $offer['product_lines'],
                startDate: $faker->optional()->date(),
                estimatedValue: $faker->optional()->randomFloat(2, 1000, 200000),
                expectedCloseDate: $faker->optional()->date(),
                successProbability: $faker->numberBetween(0, 100),
                productsOfInterest: $offer['products_of_interest'],
                generalNotes: $faker->optional(0.6)->sentence(12),
            );

            $this->opportunities->create($data);
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $items
     * @return TModel|null
     */
    private function maybePick(Collection $items, int $index, Generator $faker, int $probability): mixed
    {
        if ($items->isEmpty() || ! $faker->boolean($probability)) {
            return null;
        }

        return $items[$index % $items->count()];
    }

    /**
     * A single reward type ~40% of the time, and only for a deal that has a
     * Segnalatore to assign it to.
     *
     * @param  array<int, int>  $rewardTypeIds
     * @return array<int, int>|null
     */
    private function maybeRewards(array $rewardTypeIds, ?int $reporterId, int $index, Generator $faker): ?array
    {
        if ($rewardTypeIds === [] || $reporterId === null || ! $faker->boolean(40)) {
            return null;
        }

        return [$rewardTypeIds[$index % count($rewardTypeIds)]];
    }

    /**
     * A managers list for the deal ~50% of the time: 1 to 3 distinct users,
     * ordered (managerSyncMap turns array order into the pivot `position`).
     * `randomElements` keeps the picks unique per draw.
     *
     * @param  Collection<int, User>  $supervisors
     * @return array<int, int>|null
     */
    private function maybeManagerSlots(Collection $supervisors, Generator $faker): ?array
    {
        if ($supervisors->isEmpty() || ! $faker->boolean(50)) {
            return null;
        }

        $slotCount = $faker->numberBetween(1, min(self::MAX_MANAGER_SLOTS, $supervisors->count()));

        return $faker->randomElements($supervisors->pluck('id')->all(), $slotCount);
    }
}
