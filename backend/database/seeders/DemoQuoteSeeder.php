<?php

namespace Database\Seeders;

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\AttributeContext;
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
use Illuminate\Support\Collection;

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
 * Spec 0084 (AC-050): `attribute_values` is a per-type fake, one per QUOTE-
 * context effective attribute of the REVENUE line's own category — coherent
 * by construction, never a value for a code the quote's categories do not
 * carry (QuoteAttributeValueWriter would reject it).
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
 *
 * Spec 0087 (AC-015/D-4/D-6): every seeded Offerta gets its own "Gestori
 * Account" — GA1 + the GA2 "Operatore" slot, both always filled — submitted
 * directly on `CreateQuoteData` (`managerSlots`/`promoteManagersToOpportunity`),
 * so `QuoteService::create()`'s own writer (the SAME one POST /api/quotes
 * uses) is the ONLY thing that ever touches `quote_user` — a second, separate
 * post-create sync() call here would double-write the pivot within the same
 * run and risk a position collision (a real one, caught in review). NOT a
 * mirror of the parent Opportunity's own managers: DemoOpportunitySeeder only
 * attaches them ~50% of the time (maybeManagerSlots()), which would leave
 * half the demo Offerte with an empty GA2 and an empty Gestione Richieste
 * module after a reset (D-12's stated failure mode) — so this picks its own
 * pair of internal users instead, deterministically, and promotes them onto
 * the Opportunity when not already a member (`promoteManagersToOpportunity:
 * true`, D-6).
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
        $users = User::query()->orderBy('id')->get();

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260729);

        foreach ($opportunities as $index => $opportunity) {
            $this->quotes->create($this->buildQuoteData($faker, $index, $opportunity, $costProductIds, $users), $actor);
        }
    }

    /**
     * GA1 + the GA2 "Operatore" slot, two DISTINCT internal users (AC-015):
     * null when the demo dataset has fewer than 2 users to draw from
     * (defensive — DemoUsersSeeder always seeds far more than that), which
     * falls through to CreateQuoteData's own D-5 inheritance-from-Opportunity
     * default.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, int>|null
     */
    private function managerSlots(Generator $faker, Collection $users): ?array
    {
        if ($users->count() < 2) {
            return null;
        }

        return $faker->randomElements($users->pluck('id')->all(), 2);
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
     * @param  Collection<int, User>  $users
     */
    private function buildQuoteData(Generator $faker, int $index, Opportunity $opportunity, array $costProductIds, Collection $users): CreateQuoteData
    {
        $offerLine = $this->offerLine($faker, $index);

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
            offerLines: [$offerLine],
            costLines: [$this->costLine($faker, $costProductIds, $index)],
            // Spec 0084 (AC-050): coherent with the REVENUE line's own
            // category — resolved from the SAME QUOTE-context effective set
            // QuoteAttributeValueWriter validates against post-insert, so a
            // category with no assigned attribute simply yields an empty map.
            attributeValues: $this->attributeValues($faker, $offerLine->productId),
            managerSlots: $this->managerSlots($faker, $users),
            promoteManagersToOpportunity: true,
        );
    }

    /**
     * A value per QUOTE-context effective attribute of $productId's own
     * category (spec 0084) — a plain per-type fake, coherent enough for a
     * demo dataset without duplicating AttributeValueNormalizer's own rules.
     *
     * @return array<string, mixed>
     */
    private function attributeValues(Generator $faker, int $productId): array
    {
        $category = Product::query()->findOrFail($productId)->category;

        if ($category === null) {
            return [];
        }

        $values = [];

        foreach ($this->hierarchy->effectiveAttributes($category, AttributeContext::Quote) as $attribute) {
            $values[$attribute['code']] = $this->fakeAttributeValue($faker, $attribute);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $attribute  a CategoryHierarchy::effectiveAttributes() row
     */
    private function fakeAttributeValue(Generator $faker, array $attribute): mixed
    {
        return match ($attribute['type']) {
            'integer' => $faker->numberBetween(1, 100),
            'decimal' => $faker->randomFloat(2, 1, 1000),
            'boolean' => $faker->boolean(),
            'date' => $faker->date('Y-m-d'),
            'datetime' => $faker->date('Y-m-d\TH:i'),
            'enum' => $attribute['options'][0]['value'] ?? null,
            default => $faker->sentence(6),
        };
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
