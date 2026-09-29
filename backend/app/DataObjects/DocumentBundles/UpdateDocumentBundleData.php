<?php

declare(strict_types=1);

namespace App\DataObjects\DocumentBundles;

/**
 * Validated payload for a partial (PATCH) document bundle update (PUT/PATCH
 * /api/document-bundles/{documentBundle}, spec 0175, D-14).
 *
 * `description` is legitimately nullable on a partial PATCH, so a plain null
 * property cannot distinguish "not submitted" from "submitted as null" — the
 * `descriptionSubmitted`/`isActiveSubmitted` flags carry that distinction
 * (mirrors UpdateTaskImportanceData). `name` is `sometimes|required` at the
 * FormRequest layer, so a non-null value always means "submitted".
 */
final readonly class UpdateDocumentBundleData
{
    public function __construct(
        public ?string $name = null,
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
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
        );
    }

    /**
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

        return $attributes;
    }
}
