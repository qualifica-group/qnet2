<?php

namespace Database\Seeders;

use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\Enums\WorkOrderType;
use App\Models\Contract;
use App\Models\QuoteLine;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\Contracts\ContractActionAvailability;
use App\Services\WorkOrderService;
use Database\Seeders\Concerns\ResolvesSeedActor;
use Database\Seeders\Concerns\SeedsWithoutMail;
use Database\Seeders\Support\SampleCategoryCoverage;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

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
 * Every commessa is stamped from a Modello di Task already loaded (spec
 * 0124; the legacy ones QualificaLegacyImportSeeder migrates), the active one
 * fewest commesse use first, so every template ends up used (user directive
 * 2026-09-29). WorkOrderTaskGenerator acts as the authenticated user, as on
 * the endpoint: the seed actor stands in for it, and the task-assignment
 * mails are suppressed (the in-app notifications stay).
 *
 * COVERAGE FIRST (user directive 2026-09-29): on top of `$workOrders`, one
 * commessa on a contract carrying each contract-generating sellable category
 * no commessa covers yet (SampleCategoryCoverage).
 *
 * `$sinceOpportunityId` confines the step to the running chain's own batch.
 */
class QualificaSampleWorkOrderSeeder extends Seeder
{
    use ResolvesSeedActor;
    use SeedsWithoutMail;

    /** The batch size when the caller names none (`--work-orders` of qualifica:seed-sample). */
    public const int DEFAULT_WORK_ORDERS = 4;

    private const int MAX_START_OFFSET_DAYS = 30;

    public function __construct(
        private readonly WorkOrderService $workOrders,
        private readonly ContractActionAvailability $availability,
        private readonly SampleCategoryCoverage $coverage,
    ) {}

    public function run(int $workOrders = self::DEFAULT_WORK_ORDERS, int $sinceOpportunityId = 0): void
    {
        // Step 1: the batch's programmable contracts, the actor the task
        // generation runs as (also the supervisor of last resort for an offer
        // with neither supervisor nor operator), and the templates to stamp.
        [$contracts, $covering] = $this->programmableContracts($sinceOpportunityId, $workOrders);
        $actor = $this->resolveActor();

        if ($contracts->isEmpty() || $actor === null) {
            $this->command?->warn('Sample work orders skipped: no user, or no validated contract with an offer row still to program.');

            return;
        }

        $faker = FakerFactory::create('it_IT');
        $templateIds = $this->templateIdsByUsage();

        // Step 2: one "Programma" per contract, as the actor.
        $this->withoutMail(fn () => $this->actingAs($actor, function () use ($contracts, $faker, $actor, $templateIds): void {
            foreach ($contracts as $index => $contract) {
                $templateId = $templateIds === [] ? null : $templateIds[$index % count($templateIds)];
                $this->workOrders->create($this->buildData($faker, $index, $contract, $actor->id, $templateId));
            }
        }));

        $this->command?->info(sprintf(
            '%d sample work orders seeded, %d of them to cover a category, %d stamped from a task template.',
            $contracts->count(),
            $covering,
            $templateIds === [] ? 0 : $contracts->count(),
        ));

        if ($contracts->count() - $covering < $workOrders) {
            $this->command?->warn(sprintf('%d were requested: only validated contracts of this batch can be programmed.', $workOrders));
        }
    }

    /**
     * The batch's programmable contracts: one per category still without a
     * commessa, then up to $limit more.
     *
     * @return array{0: Collection<int, Contract>, 1: int} the contracts, and how many of them cover a category
     */
    private function programmableContracts(int $sinceOpportunityId, int $limit): array
    {
        $candidates = Contract::query()
            ->whereHas('quote', static fn ($query) => $query->where('opportunity_id', '>', $sinceOpportunityId))
            ->with(['contractStatus', 'quote.offerLines.workOrders', 'quote.opportunity.productLines'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Contract $contract): bool => $this->availability->mayProgram($contract)
                && $this->freeLineIds($contract) !== [])
            ->values();

        $covering = $this->coverage->pickCovering(
            $candidates,
            $this->coverage->unprogrammedCategoryIds(),
            static fn (Contract $contract): array => $contract->quote->opportunity->productLines->pluck('product_category_id')->all(),
        );

        return [
            $covering->merge($candidates->diff($covering)->take($limit))->values(),
            $covering->count(),
        ];
    }

    /**
     * The active templates, the ones fewest commesse were stamped from first:
     * rotated over the batch, every template is used before any repeats.
     *
     * @return list<int>
     */
    private function templateIdsByUsage(): array
    {
        return TaskTemplate::query()
            ->where('is_active', true)
            ->withCount('workOrders')
            ->orderBy('work_orders_count')
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * WorkOrderService::create() reads the generation's actor from the auth
     * guard (the endpoint's authenticated user); the previous one, if any, is
     * restored afterwards.
     */
    private function actingAs(User $actor, callable $callback): void
    {
        $previous = Auth::user();
        Auth::setUser($actor);

        try {
            $callback();
        } finally {
            if ($previous === null) {
                Auth::forgetUser();
            } else {
                Auth::setUser($previous);
            }
        }
    }

    private function buildData(Generator $faker, int $index, Contract $contract, int $fallbackSupervisorId, ?int $taskTemplateId): CreateWorkOrderData
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
            taskTemplateId: $taskTemplateId,
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
