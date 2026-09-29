<?php

declare(strict_types=1);

namespace App\DataObjects\EmailTemplates;

use App\Enums\EmailTemplateModule;

/**
 * Validated payload for creating an email template (POST /api/email-templates,
 * spec 0175, D-14). Declared DTO (no "magic flying array") — see
 * standards/architecture.md -> Data Transfer Objects.
 *
 * `body` is carried RAW (as submitted): EmailTemplateService sanitizes it
 * (D-11, App\Services\OutboundEmails\EmailHtmlSanitizer) right before
 * persisting, so attributes() deliberately does NOT include it — the caller
 * merges the sanitized value in.
 */
final readonly class CreateEmailTemplateData
{
    public function __construct(
        public string $name,
        public EmailTemplateModule $module,
        public string $subject,
        public string $body,
        public ?string $description,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            module: EmailTemplateModule::from((string) $data['module']),
            subject: (string) $data['subject'],
            body: (string) $data['body'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        );
    }

    /**
     * Attributes ready for mass-assignment, EXCLUDING `body` (see class
     * docblock).
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'module' => $this->module,
            'subject' => $this->subject,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
