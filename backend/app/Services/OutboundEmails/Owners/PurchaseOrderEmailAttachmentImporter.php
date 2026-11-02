<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails\Owners;

use App\DataObjects\WorkOrderEmails\ImportAttachmentsData;
use App\Enums\DocumentLayoutModule;
use App\Models\Attachment;
use App\Models\DocumentLayout;
use App\Models\OutboundEmail;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\User;
use App\Services\OutboundEmails\OutboundEmailAttachmentService;
use App\Services\PurchaseOrders\PurchaseOrderLayoutUnavailableException;
use App\Services\PurchaseOrders\PurchaseOrderPdfRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The attachment import sources of a purchase order email (spec 0210):
 * `purchase_order_pdf` (generated on the fly) and the supplier registry's
 * `documents`. Sources are COPIED onto the email's own collection.
 */
final class PurchaseOrderEmailAttachmentImporter
{
    public const string SOURCE_PDF = 'purchase_order_pdf';

    public const string SOURCE_DOCUMENTS = 'documents';

    private const string DOCUMENTS_COLLECTION = 'documents';

    public function __construct(
        private readonly OutboundEmailAttachmentService $emailAttachments,
        private readonly PurchaseOrderPdfRenderer $pdfRenderer,
    ) {}

    public function import(PurchaseOrder $order, string $source, ImportAttachmentsData $data, OutboundEmail $email, User $actor): OutboundEmail
    {
        return $source === self::SOURCE_DOCUMENTS
            ? $this->importDocuments($order, $email, $actor, $data->attachmentIds)
            : $this->importPdf($order, $email, $actor, $data->layoutId);
    }

    public function importPdf(PurchaseOrder $order, OutboundEmail $email, User $actor, ?int $layoutId = null): OutboundEmail
    {
        Gate::forUser($actor)->authorize('view', $order);

        $layout = $layoutId === null ? null : DocumentLayout::query()
            ->where('module', DocumentLayoutModule::PurchaseOrders->value)
            ->where('is_active', true)
            ->find($layoutId);

        if ($layoutId !== null && $layout === null) {
            throw ValidationException::withMessages(['layout_id' => [__('document_layouts.purchase_order_layout_not_available')]]);
        }

        try {
            $pdf = $this->pdfRenderer->render($order, $actor, $layout);
        } catch (PurchaseOrderLayoutUnavailableException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->emailAttachments->storePdf($email, $pdf['filename'], $pdf['bytes'], $actor);
    }

    /**
     * @param  array<int, int>  $attachmentIds
     */
    private function importDocuments(PurchaseOrder $order, OutboundEmail $email, User $actor, array $attachmentIds): OutboundEmail
    {
        Gate::forUser($actor)->authorize('viewDocuments', Registry::class);

        $sources = Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->where('collection', self::DOCUMENTS_COLLECTION)
            ->where('attachable_type', (new Registry)->getMorphClass())
            ->where('attachable_id', $order->supplier_id)
            ->get();

        if ($sources->count() !== count(array_unique($attachmentIds))) {
            throw ValidationException::withMessages(['attachment_ids' => [__('outbound_emails.attachment_not_available')]]);
        }

        return $this->emailAttachments->copyAll($sources, $email, $actor);
    }
}
