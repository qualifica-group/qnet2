<?php

namespace App\DataObjects\Sectors;

/**
 * Validated payload for creating a sector (POST /api/sectors,
 * spec 0018). Declared DTO (no "magic flying array") so the
 * StoreSectorRequest → SectorService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 */
final readonly class CreateSectorData
{
    public function __construct(
        public string $name,
        public ?int $parentId = null,
        /** Spec 0212: a plain per-node flag, defaulting to active when omitted. */
        public bool $isActive = true,
        /** Spec 0213: verbatim code, null = none. */
        public ?string $code = null,
    ) {}

    /**
     * Build from the validated StoreSectorRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            parentId: array_key_exists('parent_id', $data) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            code: isset($data['code']) ? (string) $data['code'] : null,
        );
    }
}
