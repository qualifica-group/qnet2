<?php

declare(strict_types=1);

namespace App\DataObjects\RequestManagement;

use App\DataObjects\Quotes\QuoteLineData;
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
 * Segnalatore); `supervisorId` (spec 0097, D-9, user directive 2026-09-02)
 * is the created Offerta's commission-recipient Supervisore, and carries the
 * `*Submitted` flag CreateQuoteData uses for the same field: this form
 * applies NO default of its own, so an omitted key must reach QuoteService as
 * "not submitted" and let its snapshot inheritance run untouched, which a
 * plain nullable property cannot express. `rewards` are the reward-type ids assigned to that reporter
 * (spec 0059), already reduced to a deduplicated id list by
 * StoreRequestRequest — `null` means the key was absent (nothing to assign).
 * `managerSlots` is the Offerta's TEAM of Gestori Account (spec 0097, D-1:
 * it replaced the lone `operatorId`, the GA2 "Operatore" of the user
 * directive 2026-07-29 — that operator is now slot
 * `ManagerPositions::OPERATOR` of this same collection), submitted only by an
 * actor holding `request-management.assignOperator` — the controller rejects
 * a filled slot otherwise, so it is already authorized here.
 * `operationalSiteId` is the Sede operativa (spec 0056, user directive
 * 2026-07-31): the same field the work panel edits, available at creation
 * because it is what scopes the operator list the form offers.
 *
 * For those two alone, `null` does NOT mean "leave unset": it means "the
 * creating actor and the actor's own Sede" (user directive 2026-08-04), the
 * default RequestCreationService applies — which is what covers an actor who
 * never sees the two fields at all. For `managerSlots` the same default
 * reaches one slot only: an absent payload, or one leaving the OPERATOR slot
 * empty, puts the creating actor THERE (spec 0097, AC-002) and leaves every
 * other slot exactly as submitted.
 *
 * The last three (user directive 2026-07-31, "la create il piu' simile
 * possibile al pannello") are operative fields the work panel edits, all
 * OPTIONAL at creation: `nextCallbackAt` (the planned follow-up call),
 * `generalNotes` and `attributeValues` (the "Informazioni aggiuntive", back
 * on this channel by user directive 2026-08-07 — no longer the Opportunity's
 * own dimension that spec 0084 D-1 removed, but the created Offerta's, where
 * RequestCreationService writes them). `null` means "not submitted" for each.
 *
 * No working-status pair here: the create form does not offer one (the set
 * depends on criteria the server resolves), so the Offerta is born on the
 * `open` row QuoteService bootstraps, exactly as in the Offerte module.
 *
 * `offerLines` (user directive 2026-08-07) are the created Offerta's own
 * REVENUE rows, handed to QuoteService::create() verbatim: `null` means the
 * key was absent — spec 0086 AC-028's "born with no offer line", still the
 * common case, no longer the only one.
 */
final readonly class CreateRequestData
{
    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $productLines
     * @param  array<int, int>|null  $productsOfInterest  product ids, `null` when the key was absent (nothing to record yet); already checked against `productLines` by StoreRequestRequest (user directive 2026-07-31)
     * @param  array<int, int>|null  $rewards  reward-type ids synced by RewardAssignmentWriter (beneficiary = the created Opportunity's reporter)
     * @param  array<string, mixed>|null  $attributeValues  submitted dynamic values keyed by attribute `code`, validated post-insert against the applicable set the inserted product lines produce; `null` when the key was absent
     * @param  array<int, QuoteLineData>|null  $offerLines  the created Offerta's REVENUE rows, `null` when the key was absent
     * @param  array<int, int|null>|null  $managerSlots  the Offerta's team, ordered and gap-aware (index+1 = "G.A. n" position); `null` when the key was absent
     */
    public function __construct(
        public ?int $registryId,
        public ?ProfileData $clientProfile,
        public array $productLines,
        public ?int $sourceId = null,
        public ?int $reporterId = null,
        public ?int $supervisorId = null,
        public bool $supervisorIdSubmitted = false,
        public ?array $productsOfInterest = null,
        public ?array $rewards = null,
        public ?array $managerSlots = null,
        public ?int $operationalSiteId = null,
        public ?string $nextCallbackAt = null,
        public ?string $generalNotes = null,
        public ?array $attributeValues = null,
        public ?array $offerLines = null,
    ) {}
}
