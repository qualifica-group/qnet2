<?php

namespace Database\Seeders\ManagementControl;

use App\DataObjects\Contracts\UpdateContractData;
use App\DataObjects\Contracts\ValidateContractData;
use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\Enums\WorkflowStatusSystemKey;
use App\Enums\WorkOrderType;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ContractActionService;
use App\Services\ContractService;
use App\Services\OpportunityService;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Services\QuoteService;
use App\Services\WorkOrderService;
use Carbon\CarbonImmutable;
use Faker\Generator;

/**
 * The sales cycle of one planned deal, as dated events on the timeline: the
 * opportunity and its offer on the opening day, the outcome on the close day
 * (a won offer opens its contract), the contract validation and the commessa with
 * every revenue line on its start day, which hands the lines to McDelivery.
 */
final class McSales
{
    private const array LOST_NOTES = [
        'Offerta non accettata: budget del cliente non disponibile.',
        'Il cliente ha scelto un fornitore concorrente.',
        'Progetto rinviato al prossimo esercizio.',
        'Prezzo ritenuto troppo alto dal cliente.',
    ];

    private const string WON_NOTE = 'Offerta accettata dal cliente.';

    private const int REPORTER_PERCENT = 30;

    private const int CONTRACT_MONTHS = 12;

    public function __construct(
        private readonly OpportunityService $opportunities,
        private readonly QuoteService $quotes,
        private readonly QuoteWorkflowResolver $workflows,
        private readonly ContractService $contracts,
        private readonly ContractActionService $contractActions,
        private readonly WorkOrderService $workOrders,
        private readonly McQuoteLines $lines,
        private readonly McDelivery $delivery,
    ) {}

    /**
     * @param  array<string, mixed>  $deal
     */
    public function schedule(McTimeline $timeline, McContext $context, Generator $faker, array $deal, int $number): void
    {
        $deal['company'] = $context->companies[$deal['customer_id'] % count($context->companies)];
        $deal['agent'] = $faker->randomElement($context->agents);
        $deal['reporter'] = $faker->boolean(self::REPORTER_PERCENT) ? $faker->randomElement($context->reporters) : null;
        $deal['title'] = sprintf('%s%s - %s', McCatalogue::TITLE_PREFIX, $deal['product'], $number);

        $timeline->at($deal['opened_on'], function () use ($timeline, $context, $faker, $deal): void {
            $quote = $this->open($context, $deal);

            $timeline->at($deal['closes_on'], function () use ($timeline, $context, $faker, $deal, $quote): void {
                if (! $deal['won']) {
                    $this->close($quote, WorkflowStatusSystemKey::ClosedLost, $faker->randomElement(self::LOST_NOTES), $context);

                    return;
                }

                $contract = $this->win($quote, $deal, $context);
                $timeline->at($deal['validated_on'], fn () => $contract === null ? null : $this->validate($contract, $deal, $context));
                $timeline->at($deal['starts_on'], function () use ($timeline, $context, $faker, $deal, $quote): void {
                    $workOrder = $this->program($quote, $deal, $faker, $context);
                    $this->delivery->schedule($timeline, $context, $faker, $deal, $workOrder);
                });
            });
        });
    }

    /**
     * @param  array<string, mixed>  $deal
     */
    private function open(McContext $context, array $deal): Quote
    {
        $product = $context->products[$deal['product']];
        $offerLines = $this->lines->offerLines($deal, $product);
        $categoryFunction = collect($context->categories)->firstWhere('category_id', $product->category_id);

        // Step 1: the opportunity the offer hangs from
        $opportunity = $this->opportunities->create(new CreateOpportunityData(
            registryId: $deal['customer_id'],
            referentId: null,
            commercialId: $deal['agent']->id,
            reporterId: $deal['reporter']?->id,
            supervisorId: $context->actor->id,
            sourceId: null,
            leadId: null,
            managerSlots: null,
            productLines: [['business_function_id' => $categoryFunction['business_function_id'], 'product_category_id' => $product->category_id]],
            startDate: $deal['opened_on'],
            estimatedValue: round(array_sum(array_map(fn (QuoteLineData $line): float => $line->quantity * $line->unitPrice, $offerLines)), 2),
            expectedCloseDate: $deal['closes_on'],
            successProbability: $deal['probability'],
            productsOfInterest: [$product->id],
            name: $deal['title'],
        ));

        // Step 2: the offer with its revenue lines and the budgeted cost lines
        return $this->quotes->create(new CreateQuoteData(
            code: null,
            title: $deal['title'],
            opportunityId: $opportunity->id,
            workflowStatusId: null,
            note: null,
            commercialId: $deal['agent']->id,
            commercialIdSubmitted: true,
            reporterId: $deal['reporter']?->id,
            reporterIdSubmitted: true,
            supervisorId: $context->actor->id,
            supervisorIdSubmitted: true,
            internalNotes: null,
            offerLines: $offerLines,
            costLines: $this->lines->costLines($deal, $context),
            companyId: $deal['company']['company_id'],
            companySiteId: $deal['company']['company_site_id'],
            paymentMethodId: $context->customerPaymentMethodIds[$deal['customer_id'] % count($context->customerPaymentMethodIds)],
            quoteDate: $deal['opened_on'],
        ), $context->actor);
    }

    /**
     * Closes the offer as won (the lifecycle opens its contract) and gives the contract its expiry.
     *
     * @param  array<string, mixed>  $deal
     */
    private function win(Quote $quote, array $deal, McContext $context): ?Contract
    {
        $this->close($quote, WorkflowStatusSystemKey::ClosedWon, self::WON_NOTE, $context);
        $contract = Contract::query()->where('quote_id', $quote->id)->first();

        if ($contract !== null) {
            $expiry = CarbonImmutable::parse($deal['validated_on'])->addMonths(self::CONTRACT_MONTHS);
            $this->contracts->update($contract, new UpdateContractData(
                renewalDate: $expiry->subMonth()->toDateString(),
                renewalDateSubmitted: true,
                expiryDate: $expiry->toDateString(),
                expiryDateSubmitted: true,
            ));
        }

        return $contract;
    }

    private function close(Quote $quote, WorkflowStatusSystemKey $key, string $note, McContext $context): void
    {
        $status = $this->workflows->statusesFor($this->workflows->resolve($quote))->firstWhere('system_key', $key->value);

        if ($status !== null) {
            $this->quotes->update($quote->refresh(), new UpdateQuoteData(
                workflowStatusId: $status->id,
                workflowStatusIdSubmitted: true,
                note: $status->requires_note ? $note : null,
            ), $context->actor);
        }
    }

    /**
     * @param  array<string, mixed>  $deal
     */
    private function validate(Contract $contract, array $deal, McContext $context): void
    {
        $this->contractActions->validate($contract->refresh(), new ValidateContractData(validatedAt: $deal['validated_on'], validatedAtSubmitted: true), $context->actor);
    }

    /**
     * @param  array<string, mixed>  $deal
     */
    private function program(Quote $quote, array $deal, Generator $faker, McContext $context): WorkOrder
    {
        $hired = $context->supervisors->filter(fn (User $user): bool => $user->employment->hired_at->toDateString() <= $deal['starts_on']);
        $supervisor = $hired->isEmpty() ? $context->actor : $faker->randomElement($hired->all());

        return $this->workOrders->create(CreateWorkOrderData::forContractGeneration(
            quoteId: $quote->id,
            title: $deal['title'],
            type: $deal['kind'] === 'training' ? WorkOrderType::Processing : $faker->randomElement(WorkOrderType::cases()),
            startDate: $deal['starts_on'],
            supervisorIds: [$supervisor->id],
            quoteLineIds: $quote->offerLines()->orderBy('sort_order')->orderBy('id')->pluck('id')->all(),
        ));
    }
}
