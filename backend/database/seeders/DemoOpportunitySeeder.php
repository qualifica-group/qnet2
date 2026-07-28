<?php

namespace Database\Seeders;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\OpportunityService;
use App\Services\ProductCategories\CategoryHierarchy;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the opportunities module (spec 0040): round-robins
 * existing registries (the only mandatory relation, D-4) against the
 * optional lookups for a batch of STANDALONE deals, then generates a
 * handful more FROM existing leads (BR-1) so the demo grid exercises both
 * creation paths and the `locked_fields`/`lead` detail shape.
 *
 * Every opportunity is created through OpportunityService::create() — the
 * same path POST /api/opportunities uses — so this exercises the real write
 * path (including the BR-1 derivation), not a raw insert. Idempotent:
 * existing opportunities are cleared first (harmless — nothing else
 * references an Opportunity, restrictOnDelete only runs the OTHER way).
 *
 * Depends on DemoRegistrySeeder (mandatory) plus DemoBusinessFunctionSeeder/
 * DemoReferentSeeder/DemoUsersSeeder/DemoSourceSeeder/DemoLeadSeeder
 * (optional, seeded earlier in DemoDataSeeder) — a no-op (nothing to seed) if
 * registries is empty. Product categories are client reference data
 * (QualificaCatalogSeeder), not part of the demo dataset: with none present
 * the product lines stay empty.
 *
 * Amendment rev.3: `business_function_id`/`product_category_id` are REPLACED
 * by `product_lines` — a category is only ever picked alongside its own
 * EFFECTIVE business function (see `productLineCandidates`), so every seeded
 * row already satisfies the withValidator pairing rule. User directive
 * 2026-07-17: `company_id`/`company_site_id`/`operational_site_id` are
 * REMOVED entirely.
 */
class DemoOpportunitySeeder extends Seeder
{
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

        if ($registries->isEmpty()) {
            // Nothing sensible to seed without the mandatory relation (D-4).
            return;
        }

        $referents = Referent::query()->orderBy('id')->get();
        $supervisors = User::query()->orderBy('id')->get();
        $sources = Source::query()->orderBy('id')->get();
        $productLineCandidates = $this->productLineCandidates();

        for ($index = 0; $index < self::STANDALONE_OPPORTUNITIES; $index++) {
            $this->createStandalone($faker, $index, $registries, $referents, $supervisors, $sources, $productLineCandidates);
        }

        $this->createFromLeads($faker, $supervisors);
    }

    /**
     * Every {business_function_id, product_category_id} pair a demo
     * opportunity may legitimately carry (amendment rev.3): a category whose
     * EFFECTIVE business function is null (neither its own nor an
     * inherited one) has no valid pairing and is excluded — a single
     * batched CategoryHierarchy call, never a query per row.
     *
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private function productLineCandidates(): array
    {
        $candidates = [];

        foreach ($this->hierarchy->effectiveBusinessFunctionSummaries() as $categoryId => $summary) {
            if ($summary !== null) {
                $candidates[] = ['business_function_id' => $summary['id'], 'product_category_id' => $categoryId];
            }
        }

        return $candidates;
    }

    /**
     * @param  Collection<int, Registry>  $registries
     * @param  Collection<int, Referent>  $referents
     * @param  Collection<int, User>  $supervisors
     * @param  Collection<int, Source>  $sources
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $productLineCandidates
     */
    private function createStandalone(
        Generator $faker,
        int $index,
        Collection $registries,
        Collection $referents,
        Collection $supervisors,
        Collection $sources,
        array $productLineCandidates,
    ): void {
        $data = new CreateOpportunityData(
            registryId: $registries[$index % $registries->count()]->id,
            referentId: $this->maybePick($referents, $index + 4, $faker, 60)?->id,
            commercialId: $this->maybePick($referents, $index + 5, $faker, 40)?->id,
            reporterId: $this->maybePick($referents, $index + 6, $faker, 30)?->id,
            supervisorId: $this->maybePick($supervisors, $index + 7, $faker, 60)?->id,
            sourceId: $this->maybePick($sources, $index + 8, $faker, 50)?->id,
            leadId: null,
            // spec 0043, D-3: null lets OpportunityService fall back to the
            // system 'new' status (SystemStatusGuard::resolveNewStatusId).
            opportunityStatusId: null,
            managerSlots: $this->maybeManagerSlots($supervisors, $faker),
            productLines: $this->maybeProductLines($productLineCandidates, $index + 9, $faker, 50),
            startDate: $faker->optional()->date(),
            estimatedValue: $faker->optional()->randomFloat(2, 1000, 200000),
            expectedCloseDate: $faker->optional()->date(),
            successProbability: $faker->optional()->numberBetween(0, 100),
        );

        $this->opportunities->create($data);
    }

    /**
     * A handful of opportunities generated FROM existing leads that do not
     * already have one (BR-1) — exercises the derivation path and the
     * `lead`/`locked_fields` detail shape the standalone batch above never
     * touches.
     *
     * @param  Collection<int, User>  $supervisors
     */
    private function createFromLeads(Generator $faker, Collection $supervisors): void
    {
        $leads = Lead::query()->doesntHave('opportunity')->orderBy('id')->limit(self::FROM_LEAD_OPPORTUNITIES)->get();

        foreach ($leads as $index => $lead) {
            $data = new CreateOpportunityData(
                registryId: null,
                referentId: null,
                commercialId: null,
                reporterId: null,
                supervisorId: $this->maybePick($supervisors, $index, $faker, 50)?->id,
                sourceId: null,
                leadId: $lead->id,
                // spec 0043, D-3: null lets OpportunityService fall back to
                // the system 'new' status (SystemStatusGuard::resolveNewStatusId).
                opportunityStatusId: null,
                managerSlots: $this->maybeManagerSlots($supervisors, $faker),
                productLines: null,
                startDate: $faker->optional()->date(),
                estimatedValue: $faker->optional()->randomFloat(2, 1000, 200000),
                expectedCloseDate: $faker->optional()->date(),
                successProbability: $faker->optional()->numberBetween(0, 100),
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
     * A single {business_function_id, product_category_id} product line
     * ~$probability% of the time, null (not submitted) otherwise.
     *
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $candidates
     * @return array<int, array{business_function_id: int, product_category_id: int}>|null
     */
    private function maybeProductLines(array $candidates, int $index, Generator $faker, int $probability): ?array
    {
        if ($candidates === [] || ! $faker->boolean($probability)) {
            return null;
        }

        return [$candidates[$index % count($candidates)]];
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
