<?php

declare(strict_types=1);

namespace App\RequestManagement;

/**
 * One attribute descriptor in an opportunity's "applicable attributes" set
 * (spec 0049, D-4/AC-022): the shape ApplicableAttributesResolver emits after
 * union-dedup-by-`code` across the opportunity's product lines' EFFECTIVE
 * category attributes (App\Services\ProductCategories\CategoryHierarchy).
 * Consumed by RequestManagementResource (display) AND
 * AttributeValueValidator/AttributeValueNormalizer (value pipeline) — the
 * SAME descriptor feeds both, so validation always matches what is shown.
 */
final readonly class ApplicableAttribute
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $relationTarget
     * @param  array<int, array{value: string, label: string, color: string|null}>  $options
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $type,
        public ?string $description,
        public ?string $helpText,
        public ?string $placeholder,
        public ?string $icon,
        public array $config,
        public ?array $relationTarget,
        public bool $isRequired,
        public int $sortOrder,
        public array $options,
        public bool $isSynced = false,
    ) {}

    /**
     * Builds from one CategoryHierarchy::effectiveAttributes() row, keeping
     * only what the applicable_attributes contract exposes — `inherited` is
     * dropped: it is a per-category concept with no meaning once attributes
     * from several categories are merged at opportunity level.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromEffectiveAttributeRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            type: (string) $row['type'],
            description: $row['description'] ?? null,
            helpText: $row['help_text'] ?? null,
            placeholder: $row['placeholder'] ?? null,
            icon: $row['icon'] ?? null,
            config: $row['config'] ?? [],
            relationTarget: $row['relation_target'] ?? null,
            isRequired: (bool) $row['is_required'],
            sortOrder: (int) $row['sort_order'],
            options: array_map(
                static fn (array $option): array => [
                    'value' => $option['value'],
                    'label' => $option['label'],
                    'color' => $option['color'] ?? null,
                ],
                $row['options'] ?? [],
            ),
        );
    }

    /**
     * A copy with `isRequired` forced true — the merge-across-categories rule
     * (any line's category requiring the code makes the merged descriptor
     * required), never used to relax an already-required descriptor.
     */
    public function withRequired(bool $isRequired): self
    {
        return new self(
            $this->id, $this->code, $this->name, $this->type, $this->description,
            $this->helpText, $this->placeholder, $this->icon, $this->config,
            $this->relationTarget, $isRequired, $this->sortOrder, $this->options, $this->isSynced,
        );
    }

    /**
     * A copy marked as shared between the offer and its work orders (spec
     * 0425, D-7): set by AttributeSetResolver, never read from a single row.
     */
    public function withSynced(): self
    {
        return new self(
            $this->id, $this->code, $this->name, $this->type, $this->description,
            $this->helpText, $this->placeholder, $this->icon, $this->config,
            $this->relationTarget, $this->isRequired, $this->sortOrder, $this->options, true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'help_text' => $this->helpText,
            'placeholder' => $this->placeholder,
            'icon' => $this->icon,
            'config' => $this->config,
            'relation_target' => $this->relationTarget,
            'is_required' => $this->isRequired,
            'sort_order' => $this->sortOrder,
            'options' => $this->options,
            'is_synced' => $this->isSynced,
        ];
    }
}
