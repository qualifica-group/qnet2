<?php

namespace App\DataObjects\PersonalData;

use App\Enums\ContactTypeEnum;

/**
 * Declared payload for creating/updating a Contact channel (see
 * standards/architecture.md → Data Transfer Objects). Built at the HTTP
 * boundary by the FormRequest and consumed by ContactService.
 */
final readonly class CreateContact
{
    public function __construct(
        public ContactTypeEnum $type,
        public string $value,
        public ?string $label = null,
        public bool $isPrimary = false,
        public ?bool $isWork = null,
    ) {}

    /**
     * The attributes ready for mass assignment on the Contact model. Forwarded
     * verbatim to Eloquent (allowed framework-array exception).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $attributes = [
            'type' => $this->type->value,
            'value' => $this->value,
            'label' => $this->label,
            'is_primary' => $this->isPrimary,
        ];

        // Spec 0272 D-7: null = the writer does not speak about the flag (a
        // re-submission by an unrelated surface), so an update leaves it alone
        // and a create takes the column default (false).
        if ($this->isWork !== null) {
            $attributes['is_work'] = $this->isWork;
        }

        return $attributes;
    }
}
