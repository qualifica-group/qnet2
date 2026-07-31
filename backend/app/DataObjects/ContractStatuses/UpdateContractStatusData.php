<?php

declare(strict_types=1);

namespace App\DataObjects\ContractStatuses;

/**
 * Validated payload for a partial (PATCH) contract status update (PUT/PATCH
 * /api/contract-statuses/{contractStatus}, spec 0072). Every field is
 * legitimately optional/nullable on a partial PATCH, so a plain null
 * property cannot distinguish "not submitted" from "submitted as null/false"
 * — the `*Submitted` flags carry that distinction explicitly, mirroring
 * `UpdateRewardStatusData`/`UpdateDocumentLayoutData`.
 *
 * `sort_order`/`system_key` are GONE — server-managed (see
 * App\Services\Statuses\StatusOrderManager). `group` —
 * App\Services\Statuses\SystemStatusGuard rejects it outright when the
 * target row is a system status. `color`, when submitted, cannot be
 * null/empty (`sometimes|required` at the FormRequest layer), so it carries
 * no submitted flag of its own — a non-null value always means "submitted".
 * `isDefault` is carried as the CLIENT'S request only:
 * App\Services\Contracts\ContractStatusDefaultManager decides, from the
 * PERSISTED model plus this request, whether the transition is valid (BR-5)
 * and applies it — submittedAttributes() never mass-assigns it directly.
 */
final readonly class UpdateContractStatusData
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?string $color = null,
        public ?string $group = null,
        public bool $groupSubmitted = false,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
        public ?bool $isDefault = null,
        public bool $isDefaultSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateContractStatusRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            color: array_key_exists('color', $data) ? (string) $data['color'] : null,
            group: array_key_exists('group', $data) ? (string) $data['group'] : null,
            groupSubmitted: array_key_exists('group', $data),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
            isDefault: array_key_exists('is_default', $data) ? (bool) $data['is_default'] : null,
            isDefaultSubmitted: array_key_exists('is_default', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update (framework array boundary) — EXCLUDING
     * `is_default`, applied separately by ContractStatusDefaultManager.
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

        if ($this->color !== null) {
            $attributes['color'] = $this->color;
        }

        if ($this->groupSubmitted) {
            $attributes['group'] = $this->group;
        }

        if ($this->isActiveSubmitted) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }
}
