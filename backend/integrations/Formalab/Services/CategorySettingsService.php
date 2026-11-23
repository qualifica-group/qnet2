<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Integrations\Formalab\Models\FormalabCategorySetting;
use Integrations\Formalab\Support\FormalabField;

/**
 * The "Formalab" section of a product category (spec 0423): its read model
 * and the write of `enabled` (root only) and of its own field map.
 */
final class CategorySettingsService
{
    public function __construct(
        private readonly CategoryFieldMapResolver $resolver,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(ProductCategory $category): array
    {
        $resolved = $this->resolver->forCategory($category);
        $owner = $resolved['field_map_owner'];
        $attributes = $this->attributesOf($category);

        return [
            'is_root' => $category->parent_id === null,
            'enabled' => $resolved['enabled'],
            'enabled_source_category' => $category->parent_id === null ? null : $this->reference($resolved['root']),
            'own_field_map' => $owner?->id === $category->id,
            'field_map' => $this->toPairs($resolved['field_map']),
            'field_map_source_category' => $owner !== null && $owner->id !== $category->id ? $this->reference($owner) : null,
            'fields' => array_map(fn (FormalabField $field): array => $this->describeField($field, $attributes), FormalabField::cases()),
            'attributes' => $attributes->map(static fn (array $row): array => [
                'code' => $row['code'], 'name' => $row['name'], 'type' => $row['type'],
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<array{formalab_field: string, attribute_code: string}>|null  $fieldMap  null = no map of its own (a child inherits)
     *
     * @throws ValidationException `enabled` overridden on a child, or a pair the category cannot hold
     */
    public function update(ProductCategory $category, ?bool $enabled, ?array $fieldMap, User $actor): void
    {
        // Step 1: `enabled` belongs to the root; a child may only repeat what it inherits
        $isRoot = $category->parent_id === null;

        if (! $isRoot && $enabled !== null && $enabled !== $this->resolver->forCategory($category)['enabled']) {
            throw ValidationException::withMessages(['enabled' => __('formalab::messages.category.enabled_inherited')]);
        }

        // Step 2: every pair must name a compatible work order attribute of the category, the key included
        if ($fieldMap !== null) {
            $this->assertMapFits($category, $fieldMap);
        }

        // Step 3: persist
        $setting = FormalabCategorySetting::query()->firstOrNew(['product_category_id' => $category->id]);
        $setting->fill(['field_map' => $fieldMap === null ? null : array_values($fieldMap), 'updated_by' => $actor->id]);

        if ($isRoot && $enabled !== null) {
            $setting->enabled = $enabled;
        }

        $setting->save();
    }

    /**
     * @param  list<array{formalab_field: string, attribute_code: string}>  $fieldMap
     *
     * @throws ValidationException
     */
    private function assertMapFits(ProductCategory $category, array $fieldMap): void
    {
        $typesByCode = $this->attributesOf($category)->pluck('type', 'code');
        $errors = [];

        foreach (array_values($fieldMap) as $index => $pair) {
            $field = FormalabField::from($pair['formalab_field']);
            $type = $typesByCode->get($pair['attribute_code']);

            if ($type === null) {
                $errors["field_map.{$index}.attribute_code"] = __('formalab::messages.category.attribute_not_in_category');
            } elseif (! in_array($type, $field->compatibleTypes(), true)) {
                $errors["field_map.{$index}.attribute_code"] = __('formalab::messages.category.attribute_incompatible');
            }
        }

        if ($fieldMap !== [] && ! in_array(FormalabField::Label->value, array_column($fieldMap, 'formalab_field'), true)) {
            $errors['field_map'] = __('formalab::messages.category.key_missing');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The work order attributes the category resolves (own and inherited).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function attributesOf(ProductCategory $category): Collection
    {
        return $this->hierarchy->effectiveAttributes($category, AttributeContext::WorkOrder);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return array<string, mixed>
     */
    private function describeField(FormalabField $field, Collection $attributes): array
    {
        $suggested = $field->suggestedAttributeCode();
        $suggestedRow = $suggested === null ? null : $attributes->firstWhere('code', $suggested);

        return [
            'key' => $field->value,
            'type' => $field->type(),
            'is_key' => $field->isKey(),
            'compatible_types' => $field->compatibleTypes(),
            'suggested_attribute_code' => $suggestedRow !== null && in_array($suggestedRow['type'], $field->compatibleTypes(), true) ? $suggested : null,
        ];
    }

    /**
     * @param  array<string, string>  $map
     * @return list<array{formalab_field: string, attribute_code: string}>
     */
    private function toPairs(array $map): array
    {
        return array_map(
            static fn (string $field, string $code): array => ['formalab_field' => $field, 'attribute_code' => $code],
            array_keys($map),
            array_values($map),
        );
    }

    /**
     * @return array{id: int, name: string}
     */
    private function reference(ProductCategory $category): array
    {
        return ['id' => $category->id, 'name' => $category->name];
    }
}
