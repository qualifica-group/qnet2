<?php

namespace Database\Seeders;

use App\DataObjects\Quotes\QuoteLineData;
use App\DataObjects\RequestManagement\CreateRequestData;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\Opportunities\RegistryOpenOpportunityGuard;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\RequestManagement\RequestCreationService;
use App\Support\ManagerPositions;
use Database\Seeders\Concerns\PicksDemoOffers;
use Database\Seeders\Concerns\PicksFreeRegistries;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The Gestione Richieste half of the sample dataset (user directive
 * 2026-09-08): fabricated requests, so the module's grid is not empty on a
 * fresh install — the same role QualificaSampleLeadSeeder and
 * QualificaSampleOpportunitySeeder play for Lead and Opportunita'.
 *
 * Every row goes through RequestCreationService::create(), the SAME path
 * POST /api/request-management uses, so the Opportunity and its Offerta are
 * born in one transaction with the derived `OPP_{id}` name, the generated
 * `code`, the bootstrapped workflow status and the `quotes.operator_id`
 * projection all produced by the real write path (spec 0086, D-5). A
 * hand-rolled Quote insert would skip every one of them.
 *
 * The team is filled explicitly rather than left to the service's own default
 * (the creating actor in the OPERATOR slot, AC-002): with a single actor for
 * the whole batch every request would land on the same GA2 and the module
 * would look empty to everybody else, since RequestManagementScope's tier-2
 * is exactly `quotes.operator_id === actor` (spec 0105). Rotating three
 * distinct accounts over G.A.1 / GA2 "Operatore" / GA3 spreads the batch over
 * the roster instead, and fills the two slots the grid shows as columns.
 *
 * Each request carries ONE priced offer row (user directive 2026-09-08),
 * handed to QuoteService verbatim through `offerLines` — the channel the user
 * directive 2026-08-07 opened on this endpoint, so spec 0086 AC-028's "born
 * with no offer line" stays the default of the ENDPOINT, not of this seeder.
 * Exactly one row, never more: an opportunity managed on a `single` product
 * category accepts a single offer row (spec 0077), and
 * RequestCreationService rejects the batch rather than trimming it.
 *
 * The row's product is drawn from the request's OWN classification — the
 * first `products_of_interest` PicksDemoOffers pairs with the primary product
 * line — so the Offerta's coverage is satisfied by a category the Opportunity
 * already carries, never by one QuoteService has to append. Price and VAT come
 * off the product itself (spec 0065, D-6), the same derivation the Offerte
 * form applies.
 *
 * Runs LAST of the sample chain because it consumes the Anagrafiche the two
 * steps before it left free — an anagrafica carries ONE open opportunity at a
 * time (user directive 2026-08-31), so the pool is shared through
 * PicksFreeRegistries, not re-derived here.
 *
 * ACCUMULATES, it does not converge (user directive 2026-09-08): every run
 * appends requests on whatever Anagrafiche are still free, so the seeder can
 * be launched again whenever more rows are wanted. The batch size is a `run()`
 * parameter — `php artisan qualifica:seed-sample --requests=30` — so one run
 * can also be made bigger instead of repeated. What caps a run is the pool
 * itself, never a guard: with no free anagrafica left the batch is empty and
 * the seeder says so. The Faker generator is deliberately UNSEEDED for the
 * same reason — a fixed seed would make every run a carbon copy of the first.
 */
class QualificaSampleRequestSeeder extends Seeder
{
    use PicksDemoOffers;
    use PicksFreeRegistries;

    /** The batch size when the caller names none (`--requests` of qualifica:seed-sample). */
    public const int DEFAULT_REQUESTS = 8;

    private const int MAX_OFFER_QUANTITY = 5;

    /** G.A. 1, the GA2 "Operatore" and the GA3 the grid shows as its own column. */
    private const int MANAGER_SLOTS = ManagerPositions::GA3;

    public function __construct(
        private readonly RequestCreationService $requests,
        private readonly CategoryHierarchy $hierarchy,
        private readonly RegistryOpenOpportunityGuard $openOpportunityGuard,
    ) {}

    public function run(int $requests = self::DEFAULT_REQUESTS): void
    {
        // Step 1: the actor the whole batch is created on behalf of. The
        // service reads its Sede and its own slot default off it, so without
        // one there is no creation path at all.
        $users = User::query()->orderBy('id')->get();
        $actor = $users->first();

        $registries = $this->freeRegistries($this->openOpportunityGuard);
        $this->loadOffers($this->hierarchy);

        // Step 2: without a free Anagrafica or an offer to fill the mandatory
        // `product_lines` with (D-3, at least one row), a seeded request would
        // be one the create form itself refuses to submit.
        if ($actor === null || $registries->isEmpty() || ! $this->hasOffers()) {
            $this->command?->warn('Sample requests skipped: no user, no free registry, or no product category pairing a business function with a product.');

            return;
        }

        $faker = FakerFactory::create('it_IT');

        $lookups = [
            'sources' => Source::query()->orderBy('id')->get(),
            'sites' => OperationalSite::query()->orderBy('id')->get(),
            'managers' => $users,
        ];

        // Step 3: the batch itself — one request per free anagrafica.
        $count = min($requests, $registries->count());

        for ($index = 0; $index < $count; $index++) {
            $this->seedRequest($faker, $index, $actor, $registries[$index], $lookups);
        }

        $this->command?->info(sprintf('%d sample requests seeded.', $count));
    }

    /**
     * @param  array{sources: Collection<int, Source>, sites: Collection<int, OperationalSite>, managers: Collection<int, User>}  $lookups
     */
    private function seedRequest(
        Generator $faker,
        int $index,
        User $actor,
        Registry $registry,
        array $lookups,
    ): void {
        $offer = $this->pickOffer($faker, $index);

        $this->requests->create($actor, new CreateRequestData(
            // The existing-client branch of the D-2 XOR: the Anagrafiche the
            // sample chain already seeded, never a second set of its own.
            registryId: $registry->getKey(),
            clientProfile: null,
            productLines: $offer['product_lines'],
            sourceId: $this->pick($lookups['sources'], $index)?->id,
            productsOfInterest: $offer['products_of_interest'],
            managerSlots: $this->managerSlots($lookups['managers'], $index),
            // The Sede the tier-3 `viewSite` visibility is evaluated on: a
            // request with none is out of scope for everyone but its own
            // Operatore (spec 0105, D-3).
            operationalSiteId: $this->pick($lookups['sites'], $index)?->id,
            nextCallbackAt: $faker->boolean(50)
                ? $faker->dateTimeBetween('now', '+2 months')->format('Y-m-d H:i:s')
                : null,
            generalNotes: $faker->boolean(60) ? $faker->sentence(12) : null,
            offerLines: [$this->offerLine($faker, $offer['products_of_interest'][0])],
        ));
    }

    /**
     * The single REVENUE row of the created Offerta, on a product the request
     * itself lists among its "prodotti di interesse" — hence on a category
     * its own product lines already cover.
     *
     * `unit_price`/`vat_rate_id` come off the product (spec 0065, D-6); the
     * amounts are RAW inputs here, rounded and frozen by the service's own
     * QuoteTotalsCalculator.
     */
    private function offerLine(Generator $faker, int $productId): QuoteLineData
    {
        $product = Product::query()->findOrFail($productId);

        return new QuoteLineData(
            productId: $product->id,
            quantity: (float) $faker->numberBetween(1, self::MAX_OFFER_QUANTITY),
            unitPrice: (float) $product->price,
            vatRateId: $product->vat_rate_id,
            sortOrder: null,
        );
    }

    /**
     * The request's team, rotated by $index so consecutive requests land on
     * different accounts. A user never fills two slots (the one-manager-one-slot
     * invariant of ValidatesManagerSlots, which the writer's own sync map
     * would silently collapse); with fewer accounts than slots the tail stays
     * empty.
     *
     * @param  Collection<int, User>  $managers
     * @return array<int, int|null>
     */
    private function managerSlots(Collection $managers, int $index): array
    {
        $slots = [];

        for ($slot = 0; $slot < self::MANAGER_SLOTS; $slot++) {
            $slots[] = $managers->count() > $slot
                ? $managers[($index + $slot) % $managers->count()]->id
                : null;
        }

        return $slots;
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
