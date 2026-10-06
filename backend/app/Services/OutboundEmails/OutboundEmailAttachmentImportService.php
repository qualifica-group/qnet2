<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\DataObjects\WorkOrderEmails\ImportAttachmentsData;
use App\Models\OutboundEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Attachment import entry point of the composer (spec 0175, D-7b/c/d;
 * generalized by owner in spec 0195, D-10): enforces the draft-only guard and
 * the owner's supported sources, then delegates the actual copy to the
 * owner's EmailOwner::import(). Owner-level authorization (the send ability)
 * stays the controller's job.
 */
final class OutboundEmailAttachmentImportService
{
    public function __construct(
        private readonly OutboundEmailAttachmentService $emailAttachments,
        private readonly EmailOwnerRegistry $owners,
    ) {}

    public function import(Model $owner, OutboundEmail $email, User $actor, ImportAttachmentsData $data): OutboundEmail
    {
        $this->emailAttachments->assertDraft($email);

        $emailOwner = $this->owners->for($owner);

        if (! in_array($data->source, $emailOwner->importSources(), true)) {
            throw ValidationException::withMessages(['source' => [__('outbound_emails.attachment_source_not_supported')]]);
        }

        return $emailOwner->import($owner, $data->source, $data, $email, $actor);
    }
}
