<?php

declare(strict_types=1);

namespace App\DataObjects\TaskPriorities;

/**
 * Validated payload for a partial (PATCH) task priority update (PUT/PATCH
 * /api/task-priorities/{taskPriority}, spec 0101, D-4).
 *
 * `description`, `icon` and `is_active` are legitimately nullable/false
 * VALUES on a partial PATCH, so a plain null property cannot distinguish
 * "not submitted" from "submitted as null/false" — the `*Submitted` flags
 * carry that distinction, mirroring UpdateContractStatusData. `name` and
 * `color` are `sometimes|required` at the FormRequest layer, so a non-null
 * value always means "submitted" and they need no flag of their own.
 *
 * `sort_order` is GONE — server-managed (AC-045).
 */
final readonly class UpdateTaskPriorityData
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
    ) {}

    /**
     * Build from the validated UpdateTaskPriorityRequest payload.
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
        );
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

        return $attributes;
    }
}
