<?php

namespace App\DataObjects\Sectors;

/**
 * Validated payload for a partial (PATCH) sector update
 * (PUT/PATCH /api/sectors/{sector}, spec 0018).
 *
 * Declared DTO (no "magic flying array") so the UpdateSectorRequest →
 * SectorService contract is explicit. `parent_id` is a legitimately
 * nullable VALUE (moving to root), so a plain null property cannot
 * distinguish "not submitted" from "submitted as null" — the
 * `parentIdSubmitted` flag carries that distinction explicitly, mirroring
 * UpdateProductCategoryData.
 */
final readonly class UpdateSectorData
{
    public function __construct(
        public ?string $name = null,
        public ?int $parentId = null,
        public bool $parentIdSubmitted = false,
        public ?bool $isActive = null,
        /** Spec 0213: nullable VALUE (clearing the code), hence the submitted flag. */
        public ?string $code = null,
        public bool $codeSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateSectorRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            parentId: array_key_exists('parent_id', $data) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null,
            parentIdSubmitted: array_key_exists('parent_id', $data),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            code: isset($data['code']) ? (string) $data['code'] : null,
            codeSubmitted: array_key_exists('code', $data),
        );
    }

    public function hasParentId(): bool
    {
        return $this->parentIdSubmitted;
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->codeSubmitted) {
            $attributes['code'] = $this->code;
        }

        if ($this->parentIdSubmitted) {
            $attributes['parent_id'] = $this->parentId;
        }

        // Spec 0212: the node's OWN flag, written verbatim; the effective
        // value is resolved at read time (SectorActivity).
        if ($this->isActive !== null) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }
}
