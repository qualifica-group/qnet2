<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Contracts\UpdateContractData;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `contracts` resource (spec 0072, MT-02): the
 * read-detail eager-load tree and the partial PATCH. Creation/deletion are
 * DELIBERATELY absent (D-6) — a contract is born and dies only through
 * `App\Services\Contracts\ContractLifecycleManager` (BR-1, MT-03).
 */
class ContractService
{
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
        'quote.company',
        'quote.companySite',
        'quote.operationalSite.addresses.city',
        'quote.commercial',
        'quote.reporter',
        'quote.supervisor',
        'quote.paymentMethod',
        'quote.offerLines.product.category',
        'quote.offerLines.vatRate',
        'quote.offerLines.commissions.recipient',
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
     */
    public function update(Contract $contract, UpdateContractData $data): Contract
    {
        DB::transaction(function () use ($contract, $data): void {
            $contract->fill($data->submittedAttributes())->save();
        });

        return $this->loadDetail($contract->fresh());
    }
}
