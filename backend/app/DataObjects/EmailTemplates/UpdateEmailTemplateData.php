<?php

declare(strict_types=1);

namespace App\DataObjects\EmailTemplates;

/**
 * Validated payload for a partial (PATCH) email template update (PUT/PATCH
 * /api/email-templates/{emailTemplate}, spec 0175, D-14). `module` is GONE —
 * immutable after creation (data_contract), enforced one layer up by
 * UpdateEmailTemplateRequest's own `prohibited` rule.
 *
 * `description` is legitimately nullable on a partial PATCH, so a plain null
 * property cannot distinguish "not submitted" from "submitted as null" — the
 * `descriptionSubmitted` flag carries that distinction (mirrors
 * UpdateTaskImportanceData). `name`/`subject`/`body` are `sometimes|required`
 * at the FormRequest layer, so a non-null value always means "submitted".
 *
 * `body` is carried RAW when submitted: EmailTemplateService sanitizes it
 * (D-11) before persisting.
 */
final readonly class UpdateEmailTemplateData
{
    public function __construct(
        public ?string $name = null,
        public ?string $subject = null,
        public ?string $body = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            subject: array_key_exists('subject', $data) ? (string) $data['subject'] : null,
            body: array_key_exists('body', $data) ? (string) $data['body'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
        );
    }

    /**
     * Only the attributes actually submitted, ready for a partial
     * mass-assignment update. `body`, when present, is still the RAW
     * submitted value — the Service sanitizes it before it reaches the
     * model.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->subject !== null) {
            $attributes['subject'] = $this->subject;
        }

        if ($this->body !== null) {
            $attributes['body'] = $this->body;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->isActiveSubmitted) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }
}
