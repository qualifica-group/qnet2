<?php

namespace Database\Seeders;

use App\DataObjects\Contracts\ChangeContractStatusData;
use App\DataObjects\Contracts\TerminateContractData;
use App\DataObjects\Contracts\UpdateContractData;
use App\DataObjects\Contracts\ValidateContractData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\Enums\ContractStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use App\Models\User;
use App\Services\ContractActionService;
use App\Services\ContractService;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Services\QuoteService;
use App\Services\RoleAssignmentGuard;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;

/**
 * Development seed for the contracts module (spec 0072). A Contract is never
 * inserted by hand (D-6): this seeder closes some already-seeded Offerte as
 * won through QuoteService::update() — the same path PATCH /api/quotes uses —
 * so ContractLifecycleManager opens the contract itself (BR-1, including the
 * spec 0091 eligibility rule). Each contract is then walked onto one lifecycle
 * shape through ContractService/ContractActionService, the services behind the
 * module's own endpoints, so the demo grid shows every state an operator can
 * reach: da validare, validato, lavorazione, disdetto, sospeso.
 *
 * Idempotent: a quote that already carries a contract is skipped, and a full
 * DemoDataSeeder run recreates the quotes (contracts cascade with them). A
 * no-op without quotes or without an actor.
 */
class DemoContractSeeder extends Seeder
{
    private const int SEED = 20260916;

    /** Every Nth quote is closed as won, so the demo dataset stays proportionate. */
    private const int QUOTE_STRIDE = 2;

    private const string SHAPE_TO_VALIDATE = 'to_validate';

    private const string SHAPE_VALIDATED = 'validated';

    private const string SHAPE_WORKING = 'working';

    private const string SHAPE_TERMINATED = 'terminated';

    private const string SHAPE_SUSPENDED = 'suspended';

    /** Rotated over the seeded contracts, one shape per contract. */
    private const array SHAPES = [
        self::SHAPE_TO_VALIDATE,
        self::SHAPE_VALIDATED,
        self::SHAPE_WORKING,
        self::SHAPE_TERMINATED,
        self::SHAPE_SUSPENDED,
    ];

    /** Mandatory note when the won/open destination row `requires_note` (AC-023). */
    private const string STATUS_CHANGE_NOTE = 'Esito registrato dal seed demo.';

    /** Upper bound (days) of an expiry close enough to raise the "in scadenza" alert. */
    private const int EXPIRING_SOON_MAX_DAYS = 20;

    public function __construct(
        private readonly QuoteService $quotes,
        private readonly QuoteWorkflowResolver $workflowResolver,
        private readonly ContractService $contracts,
        private readonly ContractActionService $contractActions,
    ) {}

    public function run(): void
    {
        $actor = $this->resolveActor();

        if ($actor === null) {
            return;
        }

        $faker = FakerFactory::create('it_IT');
        $faker->seed(self::SEED);

        $shapeIndex = 0;

        foreach ($this->quotesToClose() as $quote) {
            // Step 1: close the offer as won; the lifecycle manager opens the contract.
            $this->moveQuoteToGroup($quote, WorkflowStatusSystemKey::ClosedWon, $actor);

            $contract = Contract::query()->where('quote_id', $quote->id)->first();

            if ($contract === null) {
                continue; // Spec 0091: the offer's branch is not sold under a contract.
            }

            // Step 2: walk the contract onto its lifecycle shape.
            $this->applyShape(self::SHAPES[$shapeIndex % count(self::SHAPES)], $contract, $quote, $faker, $actor);
            $shapeIndex++;
        }
    }

    /**
     * @return iterable<int, Quote>
     */
    private function quotesToClose(): iterable
    {
        // The stride runs over EVERY quote before the contract filter, so a
        // re-run targets the same offers and converges instead of growing.
        return Quote::query()
            ->withExists('contract')
            ->orderBy('id')
            ->get()
            ->filter(fn (Quote $quote, int $index): bool => $index % self::QUOTE_STRIDE === 0 && ! $quote->contract_exists);
    }

    private function applyShape(string $shape, Contract $contract, Quote $quote, Generator $faker, User $actor): void
    {
        match ($shape) {
            self::SHAPE_TO_VALIDATE => $this->fillDetails($contract, $faker, $faker->numberBetween(90, 365)),
            self::SHAPE_VALIDATED => $this->validate($contract, $faker, $actor),
            self::SHAPE_WORKING => $this->moveToWorkingStatus($contract, $faker),
            self::SHAPE_TERMINATED => $this->terminate($contract, $faker, $actor),
            self::SHAPE_SUSPENDED => $this->moveQuoteToGroup($quote, WorkflowStatusSystemKey::Open, $actor),
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

    private function fillDetails(Contract $contract, Generator $faker, int $expiryInDays): void
    {
        $expiry = now()->addDays($expiryInDays);

        $this->contracts->update($contract, new UpdateContractData(
            renewalDate: $faker->boolean(60) ? $expiry->copy()->subMonth()->toDateString() : null,
            renewalDateSubmitted: true,
            expiryDate: $expiry->toDateString(),
            expiryDateSubmitted: true,
            paymentNotes: $faker->optional(0.5)->sentence(8),
            paymentNotesSubmitted: true,
            comments: $faker->optional(0.4)->sentence(10),
            commentsSubmitted: true,
        ));
    }

    /**
     * Moves the offer onto the row of ITS OWN resolved workflow set carrying
     * $systemKey — never a status picked from another set (spec 0083 AC-021).
     * Leaving closed_won is what suspends the contract (D-3).
     */
    private function moveQuoteToGroup(Quote $quote, WorkflowStatusSystemKey $systemKey, User $actor): void
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

    /**
     * Same actor convention as DemoQuoteSeeder::resolveActor().
     */
    private function resolveActor(): ?User
    {
        return User::query()
            ->whereHas('roles', static fn ($query) => $query->where('name', RoleAssignmentGuard::PRIVILEGED_ROLE))
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();
    }
}
