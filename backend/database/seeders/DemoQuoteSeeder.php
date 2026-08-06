<?php

namespace Database\Seeders;

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\QuoteService;
use App\Services\RoleAssignmentGuard;
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
 * Spec 0083 (AC-061): the status is NOT picked here. The seeder leaves
 * `workflowStatusId` null and lets QuoteService resolve the `open` row of the
 * set applicable to THAT quote (AC-020) — rotating over a catalogue, as this
 * seeder did against the old `quote_statuses`, would hand a quote a status
 * belonging to a workflow set that does not govern it.
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

        $actor = $this->resolveActor();

        if ($actor === null) {
            // QuoteService::create richiede un attore (audit/provvigioni): senza
            // un utente non c'e' nulla di valido da seminare.
            return;
        }

        $costProductIds = Product::query()->orderBy('id')->pluck('id')->all();

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260729);

        foreach ($opportunities as $index => $opportunity) {
            $this->quotes->create($this->buildQuoteData($faker, $index, $opportunity, $costProductIds), $actor);
        }
    }

    /**
     * L'attore per conto del quale il seeder scrive: il primo utente
     * privilegiato, con fallback sul primo utente esistente. Stessa convenzione
     * di DemoOpportunityLifecycleSeeder::resolveActor(); `whereHas` e non lo
     * scope `role()` di spatie, che esplode se il ruolo non esiste ancora
     * (run parziale) — proprio il caso che questo metodo deve sopravvivere.
     */
    private function resolveActor(): ?User
    {
        return User::query()
            ->whereHas('roles', static fn ($query) => $query->where('name', RoleAssignmentGuard::PRIVILEGED_ROLE))
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();
    }

    /**
     * @param  array<int, int>  $costProductIds
     */
    private function buildQuoteData(Generator $faker, int $index, Opportunity $opportunity, array $costProductIds): CreateQuoteData
    {
        return new CreateQuoteData(
            code: null,
            title: sprintf('Offerta %d - %s', $opportunity->id, $faker->company()),
            opportunityId: $opportunity->id,
            // Spec 0083 AC-061: lo stato NON si pesca dal catalogo, lo risolve il
            // service sul set applicabile a QUESTA offerta (riga `open`, AC-020).
            workflowStatusId: null,
            // Nessuna transizione di stato qui: l'offerta nasce sulla riga
            // `open`, e la nota e' richiesta solo dal CAMBIO verso uno stato
            // `requires_note` (AC-026), mai dalla creazione.
            note: null,
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
