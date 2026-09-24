<?php

namespace Database\Seeders;

use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\Enums\WorkOrderType;
use App\Models\Contract;
use App\Models\QuoteLine;
use App\Models\User;
use App\Services\Contracts\ContractActionAvailability;
use App\Services\WorkOrderService;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The Commesse step of the sample dataset (user directive 2026-09-24),
 * following the system's own flow Contratto -> Programma -> Commessa (spec
 * 0095): a commessa is generated only from a contract whose status sits in the
 * closed_won group — the rule ContractActionAvailability::mayProgram()
 * applies to the "Programma" action — and only on offer rows no other
 * commessa has already taken (D-4, "una riga, una sola Commessa").
 *
 * Built exactly as POST /api/contracts/{contract}/work-orders builds it:
 * CreateWorkOrderData::forContractGeneration() with the contract's own
 * `quote_id`, then WorkOrderService::create() — so the COM-000N code, the
 * REVENUE-only membership (spec 0093, D-7) and the programmed-line guard all
 * come from the real write path. One generation = one commessa with every
 * free row (D-3); the team stays empty, as the dialog leaves it (spec 0096,
 * D-5), and the supervisor is the offer's own, the same derivation the
 * production backfill uses.
 *
 * `$sinceOpportunityId` confines the step to the running chain's own batch.
 */
class QualificaSampleWorkOrderSeeder extends Seeder
{
    /** The batch size when the caller names none (`--work-orders` of qualifica:seed-sample). */
    public const int DEFAULT_WORK_ORDERS = 4;

    private const int MAX_START_OFFSET_DAYS = 30;

    public function __construct(
        private readonly WorkOrderService $workOrders,
        private readonly ContractActionAvailability $availability,
    ) {}

    public function run(int $workOrders = self::DEFAULT_WORK_ORDERS, int $sinceOpportunityId = 0): void
    {
        // Step 1: the batch's programmable contracts, and a supervisor of last
        // resort for an offer with neither supervisor nor operator.
        $contracts = $this->programmableContracts($sinceOpportunityId, $workOrders);
        $fallbackSupervisorId = User::query()->orderBy('id')->value('id');

        if ($contracts->isEmpty() || $fallbackSupervisorId === null) {
            $this->command?->warn('Sample work orders skipped: no user, or no validated contract with an offer row still to program.');

            return;
        }

        $faker = FakerFactory::create('it_IT');

        // Step 2: one "Programma" per contract.
        foreach ($contracts as $index => $contract) {
            $this->workOrders->create($this->buildData($faker, $index, $contract, (int) $fallbackSupervisorId));
        }

        $this->command?->info(sprintf('%d sample work orders seeded.', $contracts->count()));

        if ($contracts->count() < $workOrders) {
            $this->command?->warn(sprintf('%d were requested: only validated contracts of this batch can be programmed.', $workOrders));
        }
    }

    /**
     * @return Collection<int, Contract>
     */
    private function programmableContracts(int $sinceOpportunityId, int $limit): Collection
    {
        return Contract::query()
            ->whereHas('quote', static fn ($query) => $query->where('opportunity_id', '>', $sinceOpportunityId))
            ->with(['contractStatus', 'quote.offerLines.workOrders'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Contract $contract): bool => $this->availability->mayProgram($contract)
                && $this->freeLineIds($contract) !== [])
            ->take($limit)
            ->values();
    }

    private function buildData(Generator $faker, int $index, Contract $contract, int $fallbackSupervisorId): CreateWorkOrderData
    {
        $quote = $contract->quote;
        $start = ($contract->validated_at ?? now())->copy()->addDays($faker->numberBetween(0, self::MAX_START_OFFSET_DAYS));
        $types = WorkOrderType::cases();

        return CreateWorkOrderData::forContractGeneration(
            quoteId: $quote->id,
            title: sprintf('Commessa %s', $quote->title),
            type: $types[$index % count($types)],
            startDate: $start->toDateString(),
            supervisorIds: [$quote->supervisor_id ?? $quote->operator_id ?? $fallbackSupervisorId],
            quoteLineIds: $this->freeLineIds($contract),
        );
    }

    /**
     * The offer's REVENUE rows no commessa has taken yet (D-4).
     *
     * @return array<int, int>
     */
    private function freeLineIds(Contract $contract): array
    {
        return $contract->quote->offerLines
            ->filter(static fn (QuoteLine $line): bool => $line->workOrders->isEmpty())
            ->pluck('id')
            ->values()
            ->all();
    }
}
