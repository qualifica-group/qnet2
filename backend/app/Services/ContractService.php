<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Contracts\UpdateContractData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `contracts` resource (spec 0072, MT-02): the
 * read-detail eager-load tree and the partial PATCH. Creation/deletion are
 * DELIBERATELY absent (D-6) — a contract is born and dies only through
 * `App\Services\Contracts\ContractLifecycleManager` (BR-1, MT-03).
 */
class ContractService
{
    public function __construct(private readonly QuoteService $quoteService) {}

    /**
     * Relations eager-loaded for the detail read tree (ContractResource), so
     * a single query never N+1s. Covers both the contract's own lifecycle
     * relations and the full quote projection (D-1): client, opportunity,
     * amounts and lines all live on `quotes`/`quote_lines`, never duplicated
     * here.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'contractStatus',
        'statusBeforeSuspension',
        'validatedBy',
        'terminatedBy',
        'quote.opportunity.registry',
        // The opportunity's Referente with its Tipo referente (ContractResource::referent).
        'quote.opportunity.referent.referentType',
        'quote.company',
        'quote.companySite',
        'quote.operationalSite.addresses.city',
        'quote.commercial',
        'quote.reporter',
        'quote.supervisor',
        'quote.paymentMethod',
        'quote.offerLines.product.category',
        'quote.offerLines.supplier:id,name',
        // Spec 0360, D-4: the work orders a REVENUE row is assigned to (frozen row).
        'quote.offerLines.workOrders:work_orders.id,work_orders.code',
        // Spec 0099, D-5: QuoteLineResource reads the typology live through the
        // product, so it is loaded alongside the category it sits next to.
        'quote.offerLines.product.productTypology',
        'quote.offerLines.vatRate',
        'quote.offerLines.commissions.recipient',
        // Spec 0331: the offer data the detail projects read-only
        // (source, product lines, rewards, account managers, layout); the
        // manager labels read `opportunity.productLines.productCategory`.
        'quote.source',
        'quote.opportunity.productLines.productCategory',
        'quote.opportunity.productLines.businessFunction',
        'quote.managers',
        'quote.layout',
        'quote.rewards.rewardType',
    ];

    public function loadDetail(Contract $contract): Contract
    {
        return $contract->load(self::DETAIL_RELATIONS);
    }

    /**
     * Update an existing contract. Only the submitted keys are touched
     * (partial PATCH); `contract_status_id`'s existence/activeness is
     * already enforced by UpdateContractRequest, so the write here is a
     * plain, unconditional save (mirrors QuoteService::update()).
     *
     * Spec 0360, D-3: `title` and `offer_lines` belong to the origin offer and
     * go through QuoteService::update() (totals, coverage, automatic title,
     * workflow) in the same transaction, like RequestOfferLineWriter.
     */
    public function update(Contract $contract, UpdateContractData $data, ?User $actor = null): Contract
    {
        DB::transaction(function () use ($contract, $data, $actor): void {
            // Step 1: the contract's own columns.
            $contract->fill($data->submittedAttributes())->save();

            // Step 2: the offer-side keys, only when submitted.
            $quoteAttributes = $data->quoteAttributes();

            if ($quoteAttributes !== []) {
                // The actor is only needed by the offer write; a data-only update (seeders, tools) has none.
                $this->quoteService->update(
                    $contract->quote()->firstOrFail(),
                    UpdateQuoteData::fromValidated($quoteAttributes),
                    $actor ?? throw new \InvalidArgumentException('An actor is required to update the offer title or lines.'),
                );
            }
        });

        return $this->loadDetail($contract->fresh());
    }
}
