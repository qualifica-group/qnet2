<?php

namespace Database\Seeders;

use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\Enums\WorkOrderType;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;

/**
 * Development seed for the work-orders module (spec 0093): one WorkOrder for
 * every other already-seeded Quote that has at least one REVENUE line, each
 * created through WorkOrderService::create() — the same path POST
 * /api/work-orders uses — so the code (COM-0001...) and the REVENUE-line
 * membership invariant (D-7) both come from the real write path, never a raw
 * insert.
 *
 * Idempotent: clears its own table before reseeding (mirrors DemoQuoteSeeder).
 * A no-op when there is no quote with at least one offer line to attach to.
 */
class DemoWorkOrderSeeder extends Seeder
{
    /** Every Nth eligible quote gets a commessa, so the demo dataset stays proportionate. */
    private const int QUOTE_STRIDE = 2;

    /** Upper bound of demo partecipanti per commessa (spec 0096); the cap itself is ManagerPositions::MAX. */
    private const int MAX_DEMO_PARTICIPANTS = 3;

    public function __construct(private readonly WorkOrderService $workOrders) {}

    public function run(): void
    {
        WorkOrder::query()->delete();

        $quotes = Quote::query()->with('offerLines')->orderBy('id')->get()
            ->filter(fn (Quote $quote): bool => $quote->offerLines->isNotEmpty())
            ->values();

        if ($quotes->isEmpty()) {
            return;
        }

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260902);

        $userIds = User::query()->orderBy('id')->pluck('id')->all();

        if ($userIds === []) {
            return;
        }

        foreach ($quotes as $index => $quote) {
            if ($index % self::QUOTE_STRIDE !== 0) {
                continue;
            }

            $this->workOrders->create($this->buildData($faker, $quote, $userIds));
        }
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function buildData(Generator $faker, Quote $quote, array $userIds): CreateWorkOrderData
    {
        $lineIds = $quote->offerLines->pluck('id')->take(2)->all();
        $isForceClosed = $faker->boolean(20);
        // Spec 0096: prefer the offer's own supervisor/operator, exactly the
        // derivation the production backfill uses, so the demo data reads the
        // same way as migrated real data.
        $supervisorId = $quote->supervisor_id ?? $quote->operator_id ?? $faker->randomElement($userIds);

        return new CreateWorkOrderData(
            code: null,
            quoteId: $quote->id,
            title: sprintf('Commessa %s', $quote->title),
            type: $faker->randomElement(WorkOrderType::cases()),
            startDate: $faker->dateTimeBetween('-6 months', '+1 month')->format('Y-m-d'),
            callbackDate: $faker->optional(0.4)->date(),
            description: $faker->optional(0.6)->sentence(12),
            internalNotes: $faker->optional(0.3)->sentence(8),
            isForceClosed: $isForceClosed,
            forceCloseReason: $isForceClosed ? $faker->sentence(6) : null,
            quoteLineIds: $lineIds,
            supervisorIds: [$supervisorId],
            participantSlots: $this->buildParticipants($faker, $userIds, $supervisorId),
        );
    }

    /**
     * A small ordered set of Partecipanti, gap-free (the gaps are a UI
     * affordance, not something a seed needs to demonstrate) and never
     * repeating a user — the invariant ValidatesManagerSlots enforces on real
     * payloads.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int|null>
     */
    private function buildParticipants(Generator $faker, array $userIds, int $supervisorId): array
    {
        $candidates = array_values(array_filter($userIds, static fn (int $id): bool => $id !== $supervisorId));

        if ($candidates === []) {
            return [];
        }

        $size = min(self::MAX_DEMO_PARTICIPANTS, count($candidates));

        return $faker->randomElements($candidates, $faker->numberBetween(1, $size));
    }
}
