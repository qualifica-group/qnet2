<?php

declare(strict_types=1);

namespace App\DataObjects\TaskStatuses;

/**
 * Validated payload for a partial (PATCH) task status update (PUT/PATCH
 * /api/task-statuses/{taskStatus}, spec 0101, D-4).
 *
 * `description`, `icon` and `is_active` are legitimately nullable/false
 * VALUES on a partial PATCH, so a plain null property cannot distinguish
 * "not submitted" from "submitted as null/false" — the `*Submitted` flags
 * carry that distinction, mirroring UpdateContractStatusData. `name` and
 * `color` are `sometimes|required` at the FormRequest layer, so a non-null
 * value always means "submitted" and they need no flag of their own.
 *
 * `sort_order`/`system_key` is GONE — server-managed (AC-045).
 * `completionPercentage` is `sometimes|required|integer` at the FormRequest
 * layer, so it needs no submitted flag either, and neither does `group`
 * (`sometimes|required|string`): a non-null value always means "submitted".
 */
final readonly class UpdateTaskStatusData
{
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?string $icon = null,
        public bool $iconSubmitted = false,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
        public ?int $completionPercentage = null,
        public ?string $group = null,
    ) {}

    /**
     * Build from the validated UpdateTaskStatusRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            color: array_key_exists('color', $data) ? (string) $data['color'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            icon: array_key_exists('icon', $data) ? $data['icon'] : null,
            iconSubmitted: array_key_exists('icon', $data),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
            completionPercentage: array_key_exists('completion_percentage', $data) ? (int) $data['completion_percentage'] : null,
            group: array_key_exists('group', $data) ? (string) $data['group'] : null,
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update.
     * Also the exact key set App\Services\Statuses\SystemStatusGuard::
     * assertUpdatable() inspects on a system row (AC-043): it checks by KEY,
     * so anything beyond name/color/icon/completion_percentage is rejected.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->color !== null) {
            $attributes['color'] = $this->color;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->iconSubmitted) {
            $attributes['icon'] = $this->icon;
        }

        if ($this->isActiveSubmitted) {
            $attributes['is_active'] = $this->isActive;
        }

        if ($this->completionPercentage !== null) {
            $attributes['completion_percentage'] = $this->completionPercentage;
        }

        if ($this->group !== null) {
            $attributes['group'] = $this->group;
        }

        return $attributes;
    }
}
