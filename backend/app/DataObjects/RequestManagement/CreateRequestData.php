<?php

declare(strict_types=1);

namespace App\DataObjects\RequestManagement;

use App\DataObjects\Users\ProfileData;

/**
 * Validated payload for POST /api/request-management (spec 0057): the
 * client anagraphic block is EXACTLY one of the two mutually-exclusive
 * branches (D-2) — `registryId` (an existing Registry) or `clientProfile` (a
 * brand-new one, built from the submitted `client_identity`/`client_contacts`/
 * `client_address`) — StoreRequestRequest's own rules already enforce the XOR,
 * so exactly one of the two is non-null here. `productLines` is always
 * present (D-3, at least one row).
 *
 * `sourceId`/`reporterId` are the request's initial attribution (Fonte,
 * Segnalatore); `rewards` are the reward-type ids assigned to that reporter
 * (spec 0059), already reduced to a deduplicated id list by
 * StoreRequestRequest — `null` means the key was absent (nothing to assign).
 * `operatorId` is the GA2 "Operatore" (user directive 2026-07-29): assigning
 * ANOTHER user needs `request-management.assignOperator` — the controller
 * rejects it otherwise, so it is already authorized here — while an actor
 * without that ability always gets themselves (see withOperator()).
 * `operationalSiteId` is the Sede operativa (spec 0056, user directive
 * 2026-07-31): the same field the work panel edits, available at creation
 * because it is what scopes the operator list the form offers.
 *
 * The last five (user directive 2026-07-31, "la create il piu' simile
 * possibile al pannello") are the operative fields the work panel edits, all
 * OPTIONAL at creation: `workflowStatusId` + its `statusNote` (the working
 * state and the note a `requires_note` status demands), `nextCallbackAt`
 * (the planned follow-up call), `generalNotes` and `attributeValues` (the
 * dynamic per-category fields). `null` means "not submitted" for each: the
 * status then stays whatever OpportunityWorkflowResolver derives, and the
 * other four simply stay unset.
 */
final readonly class CreateRequestData
{
    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $productLines
     * @param  array<int, int>|null  $productsOfInterest  product ids, `null` when the key was absent (nothing to record yet); already checked against `productLines` by StoreRequestRequest (user directive 2026-07-31)
     * @param  array<int, int>|null  $rewards  reward-type ids synced by RewardAssignmentWriter (beneficiary = the created Opportunity's reporter)
     * @param  array<string, mixed>|null  $attributeValues  submitted dynamic values keyed by attribute `code`, validated post-insert by RequestAttributeValueWriter against the applicable set; `null` when the key was absent
     */
    public function __construct(
        public ?int $registryId,
        public ?ProfileData $clientProfile,
        public array $productLines,
        public ?int $sourceId = null,
        public ?int $reporterId = null,
        public ?array $productsOfInterest = null,
        public ?array $rewards = null,
        public ?int $operatorId = null,
        public ?int $operationalSiteId = null,
        public ?int $workflowStatusId = null,
        public ?string $statusNote = null,
        public ?string $nextCallbackAt = null,
        public ?string $generalNotes = null,
        public ?array $attributeValues = null,
    ) {}

    /**
     * The same payload with the GA2 operator forced to $operatorId. Used by
     * RequestManagementController::store() for an actor who may NOT assign
     * somebody else: their only legal operator is themselves, and it is
     * applied by default (user directive 2026-08-03).
     */
    public function withOperator(int $operatorId): self
    {
        return new self(
            registryId: $this->registryId,
            clientProfile: $this->clientProfile,
            productLines: $this->productLines,
            sourceId: $this->sourceId,
            reporterId: $this->reporterId,
            productsOfInterest: $this->productsOfInterest,
            rewards: $this->rewards,
            operatorId: $operatorId,
            operationalSiteId: $this->operationalSiteId,
            workflowStatusId: $this->workflowStatusId,
            statusNote: $this->statusNote,
            nextCallbackAt: $this->nextCallbackAt,
            generalNotes: $this->generalNotes,
            attributeValues: $this->attributeValues,
        );
    }
}
