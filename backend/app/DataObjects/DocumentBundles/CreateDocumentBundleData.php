<?php

declare(strict_types=1);

namespace App\DataObjects\DocumentBundles;

/**
 * Validated payload for creating a document bundle (POST /api/document-bundles,
 * spec 0175, D-14). Declared DTO (no "magic flying array") — see
 * standards/architecture.md -> Data Transfer Objects. The bundle's files are
 * NOT part of this payload: they are uploaded separately through the
 * existing /api/attachments endpoints (alias `document_bundle`).
 */
final readonly class CreateDocumentBundleData
{
    public function __construct(
        public string $name,
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
            description: array_key_exists('description', $data) ? $data['description'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
