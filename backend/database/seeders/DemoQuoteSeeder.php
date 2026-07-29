<?php

namespace Database\Seeders;

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteStatus;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\QuoteService;
use Database\Seeders\Concerns\PicksDemoOffers;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;

/**
 * Development seed for the quotes module (spec 0065): one Quote per already
 * seeded Opportunity, each created through QuoteService::create() — the same
 * path POST /api/quotes uses — so the code (QUO-0001...), the persisted
 * aggregates and the opportunity coverage all come from the real write path,
 * never a raw insert.
 *
 * Every quote gets at least one REVENUE line, drawn from PicksDemoOffers' own
 * pool of categories that resolve an EFFECTIVE business function — the same
 * guarantee DemoOpportunitySeeder relies on for `products_of_interest`, so
 * the "no business function" 422 (AC-051) never fires here — and at least
 * one COST line, drawn from the whole product catalogue unrestricted (D-7:
 * a COST line never touches opportunity coverage). D-6: the offer line's
 * `unit_price` starts from the product's `price`, the cost line's from its
 * `cost`; the VAT rate on both is the product's own `vat_rate_id`.
 *
 * `quote_status_id` rotates over the WHOLE `quote_statuses` catalogue,
 * including the 3 system rows (no custom status is seeded elsewhere).
 *
 * Idempotent: clears its own quotes before reseeding. DemoDataSeeder ALSO
 * clears quotes at its own top, before it re-clears Opportunity — a Quote
 * restricts (never cascades) its Opportunity, so the opportunities could not
 * otherwise be recreated on a second full `DemoDataSeeder` run.
 *
 * A no-op when there is no opportunity to attach a quote to, or no product
 * whose category resolves an effective business function: a half-built quote
 * (missing the offer line the form itself requires) is worse than none, the
 * same "nothing VALID to seed" philosophy as DemoOpportunitySeeder.
 */
class DemoQuoteSeeder extends Seeder
{
    use PicksDemoOffers;

    private const int MAX_OFFER_QUANTITY = 5;

    private const int MAX_COST_QUANTITY = 3;

    public function __construct(
        private readonly QuoteService $quotes,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function run(): void
    {
        Quote::query()->delete();

        $opportunities = Opportunity::query()->orderBy('id')->get();
        $this->loadOffers($this->hierarchy);

        if ($opportunities->isEmpty() || ! $this->hasOffers()) {
            // Nothing VALID to seed without an opportunity to attach to, or
            // without a category that pairs a business function with a real
            // product (the same "offer" pool the offer line is drawn from).
            return;
        }

        $costProductIds = Product::query()->orderBy('id')->pluck('id')->all();
        $statusIds = QuoteStatus::query()->orderBy('sort_order')->orderBy('id')->pluck('id')->all();

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260729);

        foreach ($opportunities as $index => $opportunity) {
            $this->quotes->create($this->buildQuoteData($faker, $index, $opportunity, $costProductIds, $statusIds));
        }
    }

    /**
     * @param  array<int, int>  $costProductIds
     * @param  array<int, int>  $statusIds
     */
    private function buildQuoteData(Generator $faker, int $index, Opportunity $opportunity, array $costProductIds, array $statusIds): CreateQuoteData
    {
        return new CreateQuoteData(
            code: null,
            title: sprintf('Offerta %d - %s', $opportunity->id, $faker->company()),
            opportunityId: $opportunity->id,
            quoteStatusId: $statusIds === [] ? null : $statusIds[$index % count($statusIds)],
            commercialId: null,
            commercialIdSubmitted: false,
            reporterId: null,
            reporterIdSubmitted: false,
            supervisorId: null,
            supervisorIdSubmitted: false,
            internalNotes: $faker->optional(0.5)->sentence(10),
            offerLines: [$this->offerLine($faker, $index)],
            costLines: [$this->costLine($faker, $costProductIds, $index)],
        );
    }

    /**
     * A REVENUE line whose product belongs to a category PicksDemoOffers
     * already proved resolves an effective business function (D-7/AC-050/
     * AC-051), so QuoteService's coverage check only ever ADDS a product
     * line to the opportunity, never rejects the quote.
     */
    private function offerLine(Generator $faker, int $index): QuoteLineData
    {
        $offer = $this->offers[$index % count($this->offers)];
        $product = Product::query()->findOrFail($faker->randomElement($offer['product_ids']));

        return new QuoteLineData(
            productId: $product->id,
            quantity: (float) $faker->numberBetween(1, self::MAX_OFFER_QUANTITY),
            unitPrice: (float) $product->price,
            vatRateId: $product->vat_rate_id,
            sortOrder: null,
        );
    }

    /**
     * A COST line drawn from the whole catalogue, unrestricted (D-7): a COST
     * line never triggers opportunity coverage, so its product's category
     * does not need to resolve a business function.
     *
     * @param  array<int, int>  $costProductIds
     */
    private function costLine(Generator $faker, array $costProductIds, int $index): QuoteLineData
    {
        $product = Product::query()->findOrFail($costProductIds[$index % count($costProductIds)]);

        return new QuoteLineData(
            productId: $product->id,
            quantity: (float) $faker->numberBetween(1, self::MAX_COST_QUANTITY),
            unitPrice: (float) $product->cost,
            vatRateId: $product->vat_rate_id,
            sortOrder: null,
        );
    }
}
