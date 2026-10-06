<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\DataObjects\WorkOrderEmails\OutboundEmailDraftData;
use App\Enums\EmailTemplateModule;
use App\Enums\HttpStatusEnum;
use App\Enums\InvoicePaymentStatus;
use App\Enums\OutboundEmailPurpose;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Services\OutboundEmails\OutboundEmailService;
use App\Services\OutboundEmails\Owners\InvoiceEmailAttachmentImporter;
use App\Services\OutboundEmails\Owners\InvoiceEmailOwner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invoice-specific draft creation (spec 0195, D-12/D-13): a document draft
 * with the PDF already attached, and a payment reminder draft. Each runs in a
 * transaction: when the PDF cannot be generated (no layout: 422) no draft is
 * left behind.
 */
final class InvoiceEmailService
{
    public function __construct(
        private readonly OutboundEmailService $emails,
        private readonly InvoiceEmailAttachmentImporter $importer,
        private readonly InvoiceEmailOwner $owner,
        private readonly InvoicePaymentStatusResolver $paymentStatus,
    ) {}

    public function createDocumentDraft(Invoice $invoice, User $actor, OutboundEmailDraftData $data, bool $attachPdf): OutboundEmail
    {
        return DB::transaction(function () use ($invoice, $actor, $data, $attachPdf): OutboundEmail {
            // Step 1: the draft.
            $email = $this->emails->createDraft($invoice, $actor, $data, OutboundEmailPurpose::Document);

            // Step 2: the generated PDF.
            return $attachPdf ? $this->importer->importPdf($invoice, $email, $actor) : $email;
        });
    }

    public function createReminderDraft(Invoice $invoice, User $actor, ?int $templateId): OutboundEmail
    {
        // Step 1: only a document with overdue installments can be reminded.
        $invoice->loadMissing('installments');
        $status = $this->paymentStatus->resolve($invoice->installments, Carbon::today());

        if (! in_array($status, [InvoicePaymentStatus::Overdue, InvoicePaymentStatus::SeriouslyOverdue], true)) {
            abort(HttpStatusEnum::CONFLICT->value, __('invoice_emails.no_overdue_installments'));
        }

        return DB::transaction(function () use ($invoice, $actor, $templateId): OutboundEmail {
            // Step 2: subject/body from the template, recipient = default.
            $data = $this->reminderData($invoice, $actor, $templateId);

            // Step 3: draft + PDF.
            $email = $this->emails->createDraft($invoice, $actor, $data, OutboundEmailPurpose::Reminder);

            return $this->importer->importPdf($invoice, $email, $actor);
        });
    }

    private function reminderData(Invoice $invoice, User $actor, ?int $templateId): OutboundEmailDraftData
    {
        $to = $this->owner->defaultTo($invoice);

        if ($templateId === null) {
            return new OutboundEmailDraftData(to: $to, toSubmitted: true);
        }

        $template = EmailTemplate::query()->find($templateId);

        if ($template === null || ! $template->is_active || $template->module !== EmailTemplateModule::Invoices) {
            throw ValidationException::withMessages(['email_template_id' => [__('outbound_emails.template_not_available')]]);
        }

        $rendered = $this->owner->render((string) $template->subject, (string) $template->body, $invoice, $actor);

        return new OutboundEmailDraftData(
            emailTemplateId: $template->id,
            emailTemplateIdSubmitted: true,
            to: $to,
            toSubmitted: true,
            subject: $rendered['subject'],
            subjectSubmitted: true,
            body: $rendered['body'],
            bodySubmitted: true,
        );
    }
}
