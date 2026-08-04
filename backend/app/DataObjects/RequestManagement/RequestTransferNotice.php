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
        public int $requestId,
        public string $contactLabel,
        public ?string $originSiteLabel,
        public ?string $previousOperatorName,
    ) {}
}
