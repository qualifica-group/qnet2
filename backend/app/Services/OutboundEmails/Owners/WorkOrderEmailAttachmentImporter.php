<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails\Owners;

use App\DataObjects\WorkOrderEmails\ImportAttachmentsData;
use App\Models\Attachment;
use App\Models\DocumentBundle;
use App\Models\OutboundEmail;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DocumentLayouts\QuoteDocumentLayoutResolver;
use App\Services\DocumentLayouts\Rendering\DocumentGenerator;
use App\Services\DocumentLayouts\Rendering\DocxToPdfConverter;
use App\Services\DocumentLayouts\Rendering\QuoteRenderSubject;
use App\Services\OutboundEmails\OutboundEmailAttachmentService;
use Illuminate\Validation\ValidationException;

/**
 * The three attachment import sources of a Commessa email (spec 0175,
 * D-7b/c/d): documents, document bundle, quote PDF. Every source is COPIED
 * (never referenced) onto the email's own `email_attachments` collection.
 * `work-orders.sendEmail` stays the controller's job; this class applies the
 * SOURCE-specific gate on top (D-7: "il gate della sorgente").
 */
final class WorkOrderEmailAttachmentImporter
{
    private const string DOCUMENTS_COLLECTION = 'documents';

    public function __construct(
        private readonly OutboundEmailAttachmentService $emailAttachments,
        private readonly QuoteDocumentLayoutResolver $layoutResolver,
        private readonly DocumentGenerator $documentGenerator,
        private readonly DocxToPdfConverter $pdfConverter,
    ) {}

    public function import(WorkOrder $workOrder, string $source, ImportAttachmentsData $data, OutboundEmail $email, User $actor): OutboundEmail
    {
        return match ($source) {
            'documents' => $this->importDocuments($workOrder, $email, $actor, $data->attachmentIds),
            'document_bundle' => $this->importDocumentBundle($email, $actor, $data->documentBundleId),
            default => $this->importQuotePdf($workOrder, $email, $actor),
        };
    }

    /**
     * @param  array<int, int>  $attachmentIds
     */
    private function importDocuments(WorkOrder $workOrder, OutboundEmail $email, User $actor, array $attachmentIds): OutboundEmail
    {
        $registry = $workOrder->quote?->opportunity?->registry;

        $sources = Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->where('collection', self::DOCUMENTS_COLLECTION)
            ->where(function ($query) use ($workOrder, $registry): void {
                $query->where(fn ($q) => $q->where('attachable_type', $workOrder->getMorphClass())->where('attachable_id', $workOrder->id));

                if ($registry !== null) {
                    $query->orWhere(fn ($q) => $q->where('attachable_type', $registry->getMorphClass())->where('attachable_id', $registry->id));
                }
            })
            ->get();

        if ($sources->count() !== count(array_unique($attachmentIds))) {
            throw ValidationException::withMessages(['attachment_ids' => [__('outbound_emails.attachment_not_available')]]);
        }

        if ($sources->contains(fn (Attachment $a) => $a->attachable_type === $workOrder->getMorphClass()) && $actor->cannot('viewDocuments', WorkOrder::class)) {
            abort(403);
        }

        if ($registry !== null
            && $sources->contains(fn (Attachment $a) => $a->attachable_type === $registry->getMorphClass())
            && $actor->cannot('viewDocuments', Registry::class)) {
            abort(403);
        }

        return $this->emailAttachments->copyAll($sources, $email, $actor);
    }

    private function importDocumentBundle(OutboundEmail $email, User $actor, ?int $documentBundleId): OutboundEmail
    {
        $bundle = DocumentBundle::query()->where('is_active', true)->find($documentBundleId);

        if ($bundle === null) {
            throw ValidationException::withMessages(['document_bundle_id' => [__('outbound_emails.document_bundle_not_available')]]);
        }

        $sources = $bundle->attachments()->where('collection', self::DOCUMENTS_COLLECTION)->get();

        return $this->emailAttachments->copyAll($sources, $email, $actor);
    }

    private function importQuotePdf(WorkOrder $workOrder, OutboundEmail $email, User $actor): OutboundEmail
    {
        $quote = $workOrder->quote;

        abort_if($quote === null, 422, __('quotes.no_layout_available'));

        if ($actor->cannot('view', $quote)) {
            abort(403);
        }

        $layout = $this->layoutResolver->resolve($quote);

        abort_if($layout === null, 422, __('quotes.no_layout_available'));

        $docx = $this->documentGenerator->generate(QuoteRenderSubject::for($quote), $layout, $actor);
        $pdf = $this->pdfConverter->convert($docx);

        return $this->emailAttachments->storePdf($email, "{$quote->code}.pdf", $pdf, $actor);
    }
}
