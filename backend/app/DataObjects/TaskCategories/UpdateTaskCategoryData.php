<?php

declare(strict_types=1);

namespace App\DataObjects\TaskCategories;

/**
 * Validated payload for a partial (PATCH) task category update (PUT/PATCH
 * /api/task-categories/{taskCategory}, spec 0101, D-4).
 *
 * `description`, `icon` and `is_active` are legitimately nullable/false
 * VALUES on a partial PATCH, so a plain null property cannot distinguish
 * "not submitted" from "submitted as null/false" — the `*Submitted` flags
 * carry that distinction, mirroring UpdateContractStatusData. `name` and
 * `color` are `sometimes|required` at the FormRequest layer, so a non-null
 * value always means "submitted" and they need no flag of their own.
 *
 * `sort_order` is GONE — server-managed (AC-045).
 *
 * `parentId` (spec 0154, D-1) is null on a legitimate "move to root", so it
 * carries its own `*Submitted` flag like `description`/`icon`/`is_active`.
 * The anti-cycle guard (parent_id cannot be the category itself nor one of
 * its own descendants) is enforced by TaskCategoryService, not here — it
 * needs to walk the tree.
 */
final readonly class UpdateTaskCategoryData
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
        public ?int $parentId = null,
        public bool $parentIdSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateTaskCategoryRequest payload.
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
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            parentIdSubmitted: array_key_exists('parent_id', $data),
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

        if ($this->parentIdSubmitted) {
            $attributes['parent_id'] = $this->parentId;
        }

        return $attributes;
    }
}
