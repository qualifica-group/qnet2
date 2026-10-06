<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\Enums\OutboundEmailStatus;
use App\Models\Attachment;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Upload/remove of an OutboundEmail's OWN allegati (spec 0175, D-7a/D-8),
 * plus the small pieces OutboundEmailAttachmentImportService (D-7b/c/d)
 * reuses rather than reimplementing: the draft-only guard, the resolved
 * `email_attachments`-scoped lookup a single attachment endpoint (remove,
 * download) needs, and the reload shape every attachment mutation returns.
 * WorkOrder-level authorization stays the controller's job, exactly like
 * OutboundEmailService.
 */
final class OutboundEmailAttachmentService
{
    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly OutboundEmailAttachmentLimitChecker $limitChecker,
    ) {}

    public function upload(OutboundEmail $email, UploadedFile $file): OutboundEmail
    {
        $this->assertDraft($email);
        $this->limitChecker->assertWithinLimit($email, (int) $file->getSize());

        $this->attachments->storeFor($email, $file, OutboundEmail::ATTACHMENT_COLLECTION);

        return $this->reload($email);
    }

    public function remove(OutboundEmail $email, int $attachmentId): OutboundEmail
    {
        $this->assertDraft($email);

        $this->attachments->delete($this->resolveOwnedOrFail($email, $attachmentId));

        return $this->reload($email);
    }

    /**
     * The `email_attachments`-scoped lookup for ONE attachment of $email —
     * shared by remove() above and the nested download endpoint (which does
     * NOT require a draft, unlike remove()).
     */
    public function resolveOwnedOrFail(OutboundEmail $email, int $attachmentId): Attachment
    {
        return $email->attachments()
            ->where('collection', OutboundEmail::ATTACHMENT_COLLECTION)
            ->where('id', $attachmentId)
            ->firstOrFail();
    }

    /**
     * Copies (never references) existing attachments onto the draft: the
     * history stays intact if the original is later removed.
     *
     * @param  Collection<int, Attachment>  $sources
     */
    public function copyAll(Collection $sources, OutboundEmail $email, User $actor): OutboundEmail
    {
        $this->limitChecker->assertWithinLimit($email, (int) $sources->sum('size'));

        DB::transaction(function () use ($sources, $email, $actor): void {
            foreach ($sources as $source) {
                $this->attachments->copyTo($source, $email, OutboundEmail::ATTACHMENT_COLLECTION, $actor);
            }
        });

        return $this->reload($email);
    }

    /**
     * Stores a generated PDF binary as an allegato of the draft.
     */
    public function storePdf(OutboundEmail $email, string $fileName, string $pdf, User $actor): OutboundEmail
    {
        $this->limitChecker->assertWithinLimit($email, strlen($pdf));

        DB::transaction(fn () => $this->attachments->storeBinary(
            $email,
            OutboundEmail::ATTACHMENT_COLLECTION,
            $fileName,
            'application/pdf',
            $pdf,
            $actor,
        ));

        return $this->reload($email);
    }

    public function assertDraft(OutboundEmail $email): void
    {
        if ($email->status !== OutboundEmailStatus::Draft) {
            abort(409, __('outbound_emails.not_draft'));
        }
    }

    /**
     * @return array<int, string|\Closure>
     */
    public function detailRelations(): array
    {
        return [
            'sender',
            'attachments' => fn ($query) => $query->where('collection', OutboundEmail::ATTACHMENT_COLLECTION),
        ];
    }

    public function reload(OutboundEmail $email): OutboundEmail
    {
        return $email->fresh($this->detailRelations());
    }
}
