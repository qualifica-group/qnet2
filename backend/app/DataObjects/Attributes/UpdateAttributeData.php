<?php

namespace App\DataObjects\Attributes;

/**
 * Validated payload for a partial (PATCH) attribute update
 * (PUT/PATCH /api/attributes/{attribute}, spec 0017, aligned to
 * UpdateCustomFieldData's presentation shape — spec 0021).
 *
 * Declared DTO (no "magic flying array") so the UpdateAttributeRequest →
 * AttributeService contract is explicit — see standards/architecture.md →
 * Data Transfer Objects. `description`, `help_text`, `placeholder`, `icon`,
 * `config` and `relation_target` are all legitimately nullable VALUES
 * (clearing them is a valid PATCH), so a plain null property cannot
 * distinguish "not submitted" from "submitted as null" — the `*Submitted`
 * flags carry that distinction explicitly, mirroring UpdateCustomFieldData.
 * `options`, when submitted, is a FULL-REPLACE of the attribute's option
 * list (spec 0017).
 */
final readonly class UpdateAttributeData
{
    /**
     * @param  array<string, mixed>|null  $config
     * @param  array<string, mixed>|null  $relationTarget
     * @param  array<int, array{value: string, label: string, color?: string|null, icon?: string|null, sort_order?: int, is_default?: bool}>|null  $options
     */
    public function __construct(
        public ?string $code = null,
        public ?string $name = null,
        public ?string $type = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?string $helpText = null,
        public bool $helpTextSubmitted = false,
        public ?string $placeholder = null,
        public bool $placeholderSubmitted = false,
        public ?string $icon = null,
        public bool $iconSubmitted = false,
        public ?array $config = null,
        public bool $configSubmitted = false,
        public ?array $relationTarget = null,
        public bool $relationTargetSubmitted = false,
        public ?array $options = null,
        public ?int $currentAttributeId = null,
        public bool $currentAttributeIdSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateAttributeRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            code: array_key_exists('code', $data) ? (string) $data['code'] : null,
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            type: array_key_exists('type', $data) ? (string) $data['type'] : null,
            description: $data['description'] ?? null,
            descriptionSubmitted: array_key_exists('description', $data),
            helpText: $data['help_text'] ?? null,
            helpTextSubmitted: array_key_exists('help_text', $data),
            placeholder: $data['placeholder'] ?? null,
            placeholderSubmitted: array_key_exists('placeholder', $data),
            icon: $data['icon'] ?? null,
            iconSubmitted: array_key_exists('icon', $data),
            config: $data['config'] ?? null,
            configSubmitted: array_key_exists('config', $data),
            relationTarget: $data['relation_target'] ?? null,
            relationTargetSubmitted: array_key_exists('relation_target', $data),
            options: array_key_exists('options', $data) ? (array) $data['options'] : null,
            currentAttributeId: isset($data['current_attribute_id']) ? (int) $data['current_attribute_id'] : null,
            currentAttributeIdSubmitted: array_key_exists('current_attribute_id', $data),
        );
    }

    public function hasType(): bool
    {
        return $this->type !== null;
    }

    public function hasOptions(): bool
    {
        return $this->options !== null;
    }

    /**
     * Only the plain scalar attributes the client actually submitted, ready
     * for a partial mass-assignment update. `type` is applied by the Service
     * separately (it needs the final value for the ENUM-options guard);
     * `options` is full-replace synced separately.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->code !== null) {
            $attributes['code'] = $this->code;
        }

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->helpTextSubmitted) {
            $attributes['help_text'] = $this->helpText;
        }

        if ($this->placeholderSubmitted) {
            $attributes['placeholder'] = $this->placeholder;
        }

        if ($this->iconSubmitted) {
            $attributes['icon'] = $this->icon;
        }

        if ($this->configSubmitted) {
            $attributes['config'] = $this->config;
        }

        if ($this->relationTargetSubmitted) {
            $attributes['relation_target'] = $this->relationTarget;
        }

        if ($this->currentAttributeIdSubmitted) {
            $attributes['current_attribute_id'] = $this->currentAttributeId;
        }

        return $attributes;
    }
}
