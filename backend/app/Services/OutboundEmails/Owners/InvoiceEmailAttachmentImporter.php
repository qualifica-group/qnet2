<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails\Owners;

use App\DataObjects\WorkOrderEmails\ImportAttachmentsData;
use App\Enums\DocumentLayoutModule;
use App\Models\Attachment;
use App\Models\DocumentLayout;
use App\Models\Invoice;
use App\Models\OutboundEmail;
use App\Models\Registry;
use App\Models\User;
use App\Services\Invoices\InvoiceLayoutUnavailableException;
use App\Services\Invoices\InvoicePdfRenderer;
use App\Services\OutboundEmails\OutboundEmailAttachmentService;
use Illuminate\Validation\ValidationException;

/**
 * The attachment import sources of an invoice email (spec 0195, D-12):
 * `invoice_pdf` (generated on the fly, D-4) and the customer registry's
 * `documents`. Sources are COPIED onto the email's own collection.
 */
final class InvoiceEmailAttachmentImporter
{
    public const string SOURCE_PDF = 'invoice_pdf';

    public const string SOURCE_DOCUMENTS = 'documents';

    private const string DOCUMENTS_COLLECTION = 'documents';

    public function __construct(
        private readonly OutboundEmailAttachmentService $emailAttachments,
        private readonly InvoicePdfRenderer $pdfRenderer,
    ) {}

    public function import(Invoice $invoice, string $source, ImportAttachmentsData $data, OutboundEmail $email, User $actor): OutboundEmail
    {
        return $source === self::SOURCE_DOCUMENTS
            ? $this->importDocuments($invoice, $email, $actor, $data->attachmentIds)
            : $this->importPdf($invoice, $email, $actor, $data->layoutId);
    }

    public function importPdf(Invoice $invoice, OutboundEmail $email, User $actor, ?int $layoutId = null): OutboundEmail
    {
        if ($actor->cannot('view', $invoice)) {
            abort(403);
        }

        $layout = $layoutId === null ? null : DocumentLayout::query()
            ->where('module', DocumentLayoutModule::Invoices->value)
            ->where('is_active', true)
            ->find($layoutId);

        if ($layoutId !== null && $layout === null) {
            throw ValidationException::withMessages(['layout_id' => [__('invoice_emails.layout_not_available')]]);
        }

        try {
            $pdf = $this->pdfRenderer->render($invoice, $actor, $layout);
        } catch (InvoiceLayoutUnavailableException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->emailAttachments->storePdf($email, $pdf['filename'], $pdf['bytes'], $actor);
    }

    /**
     * @param  array<int, int>  $attachmentIds
     */
    private function importDocuments(Invoice $invoice, OutboundEmail $email, User $actor, array $attachmentIds): OutboundEmail
    {
        if ($actor->cannot('viewDocuments', Registry::class)) {
            abort(403);
        }

        $sources = Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->where('collection', self::DOCUMENTS_COLLECTION)
            ->where('attachable_type', (new Registry)->getMorphClass())
            ->where('attachable_id', $invoice->customer_registry_id)
            ->get();

        if ($sources->count() !== count(array_unique($attachmentIds))) {
            throw ValidationException::withMessages(['attachment_ids' => [__('outbound_emails.attachment_not_available')]]);
        }

        return $this->emailAttachments->copyAll($sources, $email, $actor);
    }
}
