<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\Models\OutboundEmail;
use Illuminate\Validation\ValidationException;

/**
 * The `config('outbound_emails.max_total_attachments_kb')` ceiling (spec
 * 0175, D-7), enforced BEFORE any write on every attachment
 * upload/import/send — never after, so an email is never left holding more
 * than the limit even transiently (BE-05 microtask instruction: "Limite
 * totale verificato PRIMA di scrivere").
 *
 * Shared by OutboundEmailAttachmentService (upload/remove),
 * OutboundEmailAttachmentImportService (import) and OutboundEmailService
 * (send's own re-check, D-12) so the ceiling is computed and messaged in
 * exactly one place.
 */
final class OutboundEmailAttachmentLimitChecker
{
    public function currentBytes(OutboundEmail $email): int
    {
        return (int) $email->attachments()
            ->where('collection', OutboundEmail::ATTACHMENT_COLLECTION)
            ->sum('size');
    }

    public function maxBytes(): int
    {
        return (int) config('outbound_emails.max_total_attachments_kb') * 1024;
    }

    /**
     * @throws ValidationException when the CURRENT total plus $additionalBytes
     *                             would exceed the ceiling.
     */
    public function assertWithinLimit(OutboundEmail $email, int $additionalBytes): void
    {
        if ($this->currentBytes($email) + $additionalBytes <= $this->maxBytes()) {
            return;
        }

        throw ValidationException::withMessages([
            'attachments' => [__('outbound_emails.attachments_limit_exceeded', [
                'max_kb' => (int) config('outbound_emails.max_total_attachments_kb'),
            ])],
        ]);
    }
}
