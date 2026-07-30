<?php

declare(strict_types=1);

namespace App\DataObjects\DocumentLayouts;

use App\Enums\DocumentLayoutModule;

/**
 * Validated payload for creating a document layout (POST /api/document-layouts,
 * spec 0069). Declared DTO (no "magic flying array") so the
 * StoreDocumentLayoutRequest -> DocumentLayoutService contract is explicit —
 * see standards/architecture.md -> Data Transfer Objects.
 *
 * `isDefault` carries the CLIENT'S request only — the effective, persisted
 * `is_default` is resolved by App\Services\DocumentLayouts\DocumentLayoutDefaultManager
 * (D-7a/c: the first layout of a module always becomes default, and a
 * requested default must be active), never written directly from this DTO.
 * `code`/`module` are accepted here ONLY (create-only, immutable afterwards):
 * enforced by UpdateDocumentLayoutRequest's unconditional `prohibited` rule,
 * never exposed on UpdateDocumentLayoutData.
 */
final readonly class CreateDocumentLayoutData
{
    public function __construct(
        public string $name,
        public string $code,
        public ?string $description,
        public DocumentLayoutModule $module,
        public bool $isActive,
        public bool $isDefault,
        public array $config,
    ) {}

    /**
     * Build from the validated StoreDocumentLayoutRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            code: (string) $data['code'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            module: DocumentLayoutModule::from((string) $data['module']),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            isDefault: array_key_exists('is_default', $data) ? (bool) $data['is_default'] : false,
            config: (array) $data['config'],
        );
    }

    /**
     * Attributes ready for mass-assignment, EXCLUDING `is_default` — the
     * caller (DocumentLayoutService::create()) merges in the effective value
     * resolved by DocumentLayoutDefaultManager before persisting.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'module' => $this->module,
            'is_active' => $this->isActive,
            'config' => $this->config,
        ];
    }
}
