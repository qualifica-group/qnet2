<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\DataObjects\WorkOrderEmails\OutboundEmailDraftData;
use App\Enums\OutboundEmailPurpose;
use App\Models\OutboundEmail;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\OutboundEmails\OutboundEmailService;
use App\Services\OutboundEmails\Owners\PurchaseOrderEmailAttachmentImporter;
use Illuminate\Support\Facades\DB;

/**
 * Purchase order specific draft creation (spec 0210): a document draft with
 * the PDF already attached. Runs in a transaction: when the PDF cannot be
 * generated (no layout: 422) no draft is left behind.
 */
final class PurchaseOrderEmailService
{
    public function __construct(
        private readonly OutboundEmailService $emails,
        private readonly PurchaseOrderEmailAttachmentImporter $importer,
    ) {}

    /** 409 unless the order is approved or already sent (spec 0244, D-4). */
    public function assertSendable(PurchaseOrder $order): void
    {
        if (! $order->status->isSendable()) {
            abort(409, __('purchase_orders.not_sendable_status'));
        }
    }

    public function createDocumentDraft(PurchaseOrder $order, User $actor, OutboundEmailDraftData $data, bool $attachPdf): OutboundEmail
    {
        $this->assertSendable($order);

        return DB::transaction(function () use ($order, $actor, $data, $attachPdf): OutboundEmail {
            // Step 1: the draft.
            $email = $this->emails->createDraft($order, $actor, $data, OutboundEmailPurpose::Document);

            // Step 2: the generated PDF.
            return $attachPdf ? $this->importer->importPdf($order, $email, $actor) : $email;
        });
    }
}
