<?php

declare(strict_types=1);

namespace App\DataObjects\WorkOrderEmails;

/**
 * Validated payload shared by POST (create) and PATCH (update) of a draft
 * OutboundEmail (spec 0175, data_contract: "PATCH: come POST" — every field
 * optional, a draft may be saved empty). ONE data object for both
 * StoreOutboundEmailRequest and UpdateOutboundEmailRequest — no
 * Create/Update split (unlike EmailTemplate): there is no required field
 * either operation adds.
 *
 * Each field carries its own `*Submitted` flag (mirrors
 * UpdateEmailTemplateData) so OutboundEmailService can tell "not submitted"
 * from "submitted as null/empty" on a partial PATCH; on a POST every
 * submitted key is, by construction, also present.
 *
 * `body` is carried RAW when submitted: OutboundEmailService sanitizes it
 * (D-11, EmailHtmlSanitizer) right before persisting — never here.
 */
final readonly class OutboundEmailDraftData
{
    public function __construct(
        public ?int $emailTemplateId = null,
        public bool $emailTemplateIdSubmitted = false,
        /** @var array<int, string>|null */
        public ?array $to = null,
        public bool $toSubmitted = false,
        /** @var array<int, string>|null */
        public ?array $cc = null,
        public bool $ccSubmitted = false,
        /** @var array<int, string>|null */
        public ?array $bcc = null,
        public bool $bccSubmitted = false,
        public ?string $subject = null,
        public bool $subjectSubmitted = false,
        public ?string $body = null,
        public bool $bodySubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            emailTemplateId: array_key_exists('email_template_id', $data) && $data['email_template_id'] !== null
                ? (int) $data['email_template_id'] : null,
            emailTemplateIdSubmitted: array_key_exists('email_template_id', $data),
            to: array_key_exists('to', $data) ? array_values((array) $data['to']) : null,
            toSubmitted: array_key_exists('to', $data),
            cc: array_key_exists('cc', $data) ? array_values((array) $data['cc']) : null,
            ccSubmitted: array_key_exists('cc', $data),
            bcc: array_key_exists('bcc', $data) ? array_values((array) $data['bcc']) : null,
            bccSubmitted: array_key_exists('bcc', $data),
            subject: array_key_exists('subject', $data) ? $data['subject'] : null,
            subjectSubmitted: array_key_exists('subject', $data),
            body: array_key_exists('body', $data) ? $data['body'] : null,
            bodySubmitted: array_key_exists('body', $data),
        );
    }

    /**
     * Only the attributes actually submitted, ready for a partial
     * mass-assignment (POST or PATCH alike). `body`, when present, is still
     * the RAW submitted value — the Service sanitizes it before it reaches
     * the model.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->emailTemplateIdSubmitted) {
            $attributes['email_template_id'] = $this->emailTemplateId;
        }

        if ($this->toSubmitted) {
            $attributes['to_recipients'] = $this->to;
        }

        if ($this->ccSubmitted) {
            $attributes['cc_recipients'] = $this->cc;
        }

        if ($this->bccSubmitted) {
            $attributes['bcc_recipients'] = $this->bcc;
        }

        if ($this->subjectSubmitted) {
            $attributes['subject'] = $this->subject;
        }

        if ($this->bodySubmitted) {
            $attributes['body'] = $this->body;
        }

        return $attributes;
    }
}
