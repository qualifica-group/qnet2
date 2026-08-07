<?php

declare(strict_types=1);

namespace App\DataObjects\RequestManagement;

/**
 * Per-request content accumulated by RequestTransferService::transferOne()
 * while STILL inside the write transaction — nothing is sent from there
 * (spec 0079: notifications dispatch only after commit). Carries exactly the
 * facts RequestTransferredNotification needs that vary PER request; the
 * destination Sede and the new operator are the SAME for the whole batch and
 * travel separately (RequestTransferService::dispatchNotifications()).
 */
final readonly class RequestTransferNotice
{
    public function __construct(
        /** The transferred Offerta's own id (spec 0086, D-2) — a grid row IS the Quote, not the Opportunity. */
        public int $requestId,
        public string $contactLabel,
        public ?string $originSiteLabel,
        public ?string $previousOperatorName,
        /**
         * The user who held the GA2 slot BEFORE this transfer (spec 0081):
         * they get their own "questo contatto non e' piu' tuo" notification,
         * so the id travels beside the name. Null when the request had no
         * operator at all.
         */
        public ?int $previousOperatorId = null,
        /**
         * The Offerta's own Opportunity id (spec 0086, MT-04b): the deep
         * link's `/opportunities/:id` branch needs this DISTINCT id — never
         * $requestId, which now names the Quote.
         */
        public ?int $opportunityId = null,
    ) {}
}
