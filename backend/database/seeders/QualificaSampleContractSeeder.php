<?php

namespace Database\Seeders;

use App\DataObjects\Contracts\ChangeContractStatusData;
use App\DataObjects\Contracts\TerminateContractData;
use App\DataObjects\Contracts\UpdateContractData;
use App\DataObjects\Contracts\ValidateContractData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\Enums\ContractStatusGroup;
use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use App\Models\User;
use App\Services\ContractActionService;
use App\Services\Contracts\ContractEligibility;
use App\Services\ContractService;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Services\QuoteService;
use Database\Seeders\Concerns\ResolvesSeedActor;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The Contratti step of the sample dataset (user directive 2026-09-24). A
 * Contract is never inserted by hand (spec 0072, D-6): this step closes some
 * of the batch's Offerte positively through QuoteService::update() — the path
 * PATCH /api/quotes uses — and ContractLifecycleManager opens the contract
 * itself (BR-1).
 *
 * Only offers the system would turn into a contract are closed: still in a
 * working group, carrying at least one REVENUE row (a closing group demands
 * one, spec 0102) and on a branch that is sold under a contract
 * (ContractEligibility, spec 0091 — "Formazione" is not). So `--contracts=N`
 * means N contracts, not N closed offers of which some yield nothing.
 *
 * Each contract is then walked onto one lifecycle shape through
 * ContractService/ContractActionService, the services behind the module's own
 * endpoints: da validare, validato, lavorazione (a custom status close to
 * expiry), disdetto, sospeso (the offer reopened, BR-2). Half of the rotation
 * is "validato" on purpose: it is the closed_won group "Programma" requires
 * (spec 0095), i.e. what QualificaSampleWorkOrderSeeder feeds on.
 *
 * `$sinceOpportunityId` confines the step to the running chain's own batch,
 * so a real offer is never closed by a seed. Unseeded Faker: the chain
 * accumulates.
 */
class QualificaSampleContractSeeder extends Seeder
{
    use ResolvesSeedActor;

    /** The batch size when the caller names none (`--contracts` of qualifica:seed-sample). */
    public const int DEFAULT_CONTRACTS = 8;

    private const string SHAPE_TO_VALIDATE = 'to_validate';

    private const string SHAPE_VALIDATED = 'validated';

    private const string SHAPE_WORKING = 'working';

    private const string SHAPE_TERMINATED = 'terminated';

    private const string SHAPE_SUSPENDED = 'suspended';

    /** Rotated over the seeded contracts, one shape each; every other one is validated. */
    private const array SHAPES = [
        self::SHAPE_VALIDATED,
        self::SHAPE_TO_VALIDATE,
        self::SHAPE_VALIDATED,
        self::SHAPE_WORKING,
        self::SHAPE_VALIDATED,
        self::SHAPE_TERMINATED,
        self::SHAPE_VALIDATED,
        self::SHAPE_SUSPENDED,
    ];

    /** Mandatory note when the destination row `requires_note` (spec 0083, AC-023). */
    private const string STATUS_CHANGE_NOTE = 'Esito registrato dal seed di esempio.';

    /** Upper bound (days) of an expiry close enough to raise the "in scadenza" alert. */
    private const int EXPIRING_SOON_MAX_DAYS = 20;

    public function __construct(
        private readonly QuoteService $quotes,
        private readonly QuoteWorkflowResolver $workflowResolver,
        private readonly ContractEligibility $eligibility,
        private readonly ContractService $contracts,
        private readonly ContractActionService $contractActions,
    ) {}

    public function run(int $contracts = self::DEFAULT_CONTRACTS, int $sinceOpportunityId = 0): void
    {
        // Step 1: the actor, and the batch's offers the system would turn
        // into a contract on a positive close.
        $actor = $this->resolveActor();
        $candidates = $this->closableQuotes($sinceOpportunityId, $contracts);

        if ($actor === null || $candidates->isEmpty()) {
            $this->command?->warn('Sample contracts skipped: no user, or no open offer on a branch sold under a contract.');

            return;
        }

        $faker = FakerFactory::create('it_IT');
        $seeded = 0;

        foreach ($candidates as $quote) {
            // Step 2: close the offer as won; the lifecycle manager opens the contract.
            $this->moveQuoteTo($quote, WorkflowStatusSystemKey::ClosedWon, $actor);

            $contract = Contract::query()->where('quote_id', $quote->id)->first();

            if ($contract === null) {
                continue;
            }

            // Step 3: walk the contract onto its lifecycle shape.
            $this->applyShape(self::SHAPES[$seeded % count(self::SHAPES)], $contract, $quote, $faker, $actor);
            $seeded++;
        }

        $this->command?->info(sprintf('%d sample contracts seeded.', $seeded));

        if ($seeded < $contracts) {
            $this->command?->warn(sprintf('%d were requested: only open offers of this batch on a branch sold under a contract can open one.', $contracts));
        }
    }

    /**
     * @return Collection<int, Quote>
     */
    private function closableQuotes(int $sinceOpportunityId, int $limit): Collection
    {
        return Quote::query()
            ->where('opportunity_id', '>', $sinceOpportunityId)
            ->doesntHave('contract')
            ->has('offerLines')
            ->whereHas('quoteWorkflowStatus', static fn (Builder $query) => $query->whereNotIn('group', [
                WorkflowStatusGroup::ClosedWon->value,
                WorkflowStatusGroup::ClosedLost->value,
            ]))
            ->orderBy('id')
            ->get()
            ->filter(fn (Quote $quote): bool => $this->eligibility->allowsContract($quote))
            ->take($limit)
            ->values();
    }

    private function applyShape(string $shape, Contract $contract, Quote $quote, Generator $faker, User $actor): void
    {
        match ($shape) {
            self::SHAPE_TO_VALIDATE => $this->fillDetails($contract, $faker, $faker->numberBetween(90, 365)),
            self::SHAPE_VALIDATED => $this->validate($contract, $faker, $actor),
            self::SHAPE_WORKING => $this->moveToWorkingStatus($contract, $faker),
            self::SHAPE_TERMINATED => $this->terminate($contract, $faker, $actor),
            // BR-2/D-3: leaving closed_won is what suspends the contract.
            self::SHAPE_SUSPENDED => $this->moveQuoteTo($quote, WorkflowStatusSystemKey::Open, $actor),
        };
    }

    private function validate(Contract $contract, Generator $faker, User $actor): void
    {
        $this->fillDetails($contract, $faker, $faker->numberBetween(120, 540));

        $this->contractActions->validate(
            $contract,
            new ValidateContractData(
                validatedAt: $faker->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
                validatedAtSubmitted: true,
            ),
            $actor,
        );
    }

    /**
     * A custom working status ("Da programmare", "In scadenza"...) with an
     * expiry close enough to surface the contract alert.
     */
    private function moveToWorkingStatus(Contract $contract, Generator $faker): void
    {
        $this->fillDetails($contract, $faker, $faker->numberBetween(1, self::EXPIRING_SOON_MAX_DAYS));

        $statusIds = ContractStatus::query()
            ->whereNull('system_key')
            ->where('is_active', true)
            ->whereIn('group', [ContractStatusGroup::Open->value, ContractStatusGroup::Pending->value])
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        if ($statusIds !== []) {
            $this->contractActions->changeStatus($contract, new ChangeContractStatusData((int) $faker->randomElement($statusIds)));
        }
    }

    private function terminate(Contract $contract, Generator $faker, User $actor): void
    {
        $this->fillDetails($contract, $faker, $faker->numberBetween(30, 180));

        $this->contractActions->terminate(
            $contract,
            new TerminateContractData(
                terminatedAt: $faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
                terminationReason: $faker->sentence(8),
            ),
            $actor,
        );
    }

    /**
     * "Modifica dati": expiry, and a renewal never after it (spec 0095, AC-064).
     */
    private function fillDetails(Contract $contract, Generator $faker, int $expiryInDays): void
    {
        $expiry = now()->addDays($expiryInDays);

        $this->contracts->update($contract, new UpdateContractData(
            renewalDate: $faker->boolean(60) ? $expiry->copy()->subMonth()->toDateString() : null,
            renewalDateSubmitted: true,
            expiryDate: $expiry->toDateString(),
            expiryDateSubmitted: true,
            paymentNotes: $faker->boolean(50) ? $faker->sentence(8) : null,
            paymentNotesSubmitted: true,
            comments: $faker->boolean(40) ? $faker->sentence(10) : null,
            commentsSubmitted: true,
        ));
    }

    /**
     * Moves the offer onto the row of ITS OWN resolved set carrying
     * $systemKey — never a status of another set (spec 0083, AC-021).
     */
    private function moveQuoteTo(Quote $quote, WorkflowStatusSystemKey $systemKey, User $actor): void
    {
        $status = $this->workflowResolver
            ->statusesFor($this->workflowResolver->resolve($quote))
            ->firstWhere('system_key', $systemKey->value);

        if ($status === null) {
            return;
        }

        $this->quotes->update(
            $quote->refresh(),
            new UpdateQuoteData(
                workflowStatusId: $status->id,
                workflowStatusIdSubmitted: true,
                note: $status->requires_note ? self::STATUS_CHANGE_NOTE : null,
            ),
            $actor,
        );
    }
}
