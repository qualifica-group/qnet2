<?php

declare(strict_types=1);

namespace App\DataObjects\DocumentLayouts;

/**
 * Validated payload for a partial (PATCH) document layout update (PUT/PATCH
 * /api/document-layouts/{documentLayout}, spec 0069).
 *
 * Declared DTO (no "magic flying array") so the UpdateDocumentLayoutRequest ->
 * DocumentLayoutService contract is explicit. `description` is a legitimately
 * nullable VALUE (it clears back to none) and `is_active`/`config` are
 * legitimately optional, so a plain null property cannot distinguish "not
 * submitted" from "submitted as null/false" — the `*Submitted` flags carry
 * that distinction explicitly, mirroring `UpdatePaymentMethodData`'s pattern.
 *
 * `is_default` is carried as the CLIENT'S request only, same as
 * CreateDocumentLayoutData: DocumentLayoutDefaultManager decides, from the
 * PERSISTED model plus this request, whether the transition is valid (D-7d/e)
 * and applies it — submittedAttributes() never mass-assigns it directly.
 * `code`/`module` are GONE — permanently immutable after create (D-2),
 * rejected at the FormRequest layer via an unconditional `prohibited` rule,
 * never reaching this DTO.
 */
final readonly class UpdateDocumentLayoutData
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
        public ?bool $isDefault = null,
        public bool $isDefaultSubmitted = false,
        public ?array $config = null,
        public bool $configSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateDocumentLayoutRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
            isDefault: array_key_exists('is_default', $data) ? (bool) $data['is_default'] : null,
            isDefaultSubmitted: array_key_exists('is_default', $data),
            config: array_key_exists('config', $data) ? (array) $data['config'] : null,
            configSubmitted: array_key_exists('config', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update (framework array boundary) — EXCLUDING
     * `is_default`, applied separately by DocumentLayoutDefaultManager.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->isActiveSubmitted) {
            $attributes['is_active'] = $this->isActive;
        }

        if ($this->configSubmitted) {
            $attributes['config'] = $this->config;
        }

        return $attributes;
    }
}
