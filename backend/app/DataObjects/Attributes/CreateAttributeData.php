<?php

namespace App\DataObjects\Attributes;

/**
 * Validated payload for creating an attribute (POST /api/attributes, spec
 * 0017, aligned to CreateCustomFieldData's presentation shape — spec 0021).
 * Declared DTO (no "magic flying array") so the StoreAttributeRequest →
 * AttributeService contract is explicit — see standards/architecture.md →
 * Data Transfer Objects.
 *
 * `options` is null when the client did not submit the key at all (a
 * non-ENUM attribute never carries options); an explicit empty array would
 * already have failed FormRequest validation for an ENUM attribute.
 */
final readonly class CreateAttributeData
{
    /**
     * @param  array<string, mixed>|null  $config
     * @param  array<string, mixed>|null  $relationTarget
     * @param  array<int, array{value: string, label: string, color?: string|null, icon?: string|null, sort_order?: int, is_default?: bool}>|null  $options
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $type,
        public ?string $description = null,
        public ?string $helpText = null,
        public ?string $placeholder = null,
        public ?string $icon = null,
        public ?array $config = null,
        public ?array $relationTarget = null,
        public ?array $options = null,
        public ?int $currentAttributeId = null,
    ) {}

    /**
     * Build from the validated StoreAttributeRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            name: (string) $data['name'],
            type: (string) $data['type'],
            description: $data['description'] ?? null,
            helpText: $data['help_text'] ?? null,
            placeholder: $data['placeholder'] ?? null,
            icon: $data['icon'] ?? null,
            config: $data['config'] ?? null,
            relationTarget: $data['relation_target'] ?? null,
            options: array_key_exists('options', $data) ? (array) $data['options'] : null,
            currentAttributeId: isset($data['current_attribute_id']) ? (int) $data['current_attribute_id'] : null,
        );
    }

    public function hasOptions(): bool
    {
        return $this->options !== null;
    }
}
