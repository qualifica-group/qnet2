<?php

namespace App\DataObjects\RewardStatuses;

use App\Enums\RewardStatusGroup;

/**
 * Validated payload for a partial (PATCH) reward status update (PUT/PATCH
 * /api/reward-statuses/{rewardStatus}, spec 0060).
 *
 * Declared DTO (no "magic flying array") so the UpdateRewardStatusRequest ->
 * RewardStatusService contract is explicit. `description` is a legitimately
 * nullable VALUE (it clears back to none) and `is_active` a legitimately
 * optional boolean, so a plain null property cannot distinguish "not
 * submitted" from "submitted as null/false" — `descriptionSubmitted`/
 * `isActiveSubmitted` carry that distinction explicitly, mirroring
 * `UpdateOpportunityStatusData`'s `colorSubmitted`/`groupSubmitted`. `color`
 * carries NO submitted flag (like `UpdateRewardTypeData`): it can never be
 * resubmitted as null/empty (BR-2, `sometimes|required` at the FormRequest
 * layer); `group` (spec 0073) carries none either, for the same reason —
 * `sometimes|required`, never resubmittable as null. `sort_order`/
 * `system_key` are GONE — server-managed (see
 * App\Services\Statuses\StatusOrderManager).
 */
final readonly class UpdateRewardStatusData
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?string $color = null,
        public ?RewardStatusGroup $group = null,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateRewardStatusRequest payload.
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
            group: array_key_exists('group', $data) ? RewardStatusGroup::from((string) $data['group']) : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update (framework array boundary).
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

        if ($this->group !== null) {
            $attributes['group'] = $this->group;
        }

        if ($this->isActiveSubmitted) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }
}
