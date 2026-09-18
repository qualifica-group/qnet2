<?php

namespace App\Services\ProductCategories;

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * The composition half of CategoryHierarchy::effectiveAttributes(): turns an
 * inheritance chain into the effective attribute rows, independently of HOW
 * each level's own assignments were read. Split out so the per-category read
 * (one query per level) and the batched read of every category at once
 * (CategoryHierarchy::effectiveAttributesByCategory()) share one truth about
 * the override rule and the row shape. An assignment is the attribute's
 * category-independent descriptor plus the pivot's per-category flags.
 * Stateless: static, so CategoryHierarchy keeps its argument-less construction.
 */
final class EffectiveAttributeComposer
{
    /**
     * Walks $chain root→self: the MOST SPECIFIC assignment of an attribute
     * wins, but keeps the position where the attribute FIRST appeared.
     *
     * @param  Collection<int, ProductCategory>  $chain  root-first, ending with $category
     * @param  callable(ProductCategory): iterable<int, array{descriptor: array{fields: array<string, mixed>, options: array<int, array<string, mixed>>}, is_required: bool, sort_order: int}>  $ownAssignments  a level's own assignments in $context
     * @return Collection<int, array<string, mixed>>
     */
    public static function compose(ProductCategory $category, Collection $chain, AttributeContext $context, callable $ownAssignments): Collection
    {
        $ordered = [];
        $index = [];

        foreach ($chain as $level) {
            $isOwn = $level->is($category);

            foreach ($ownAssignments($level) as $assignment) {
                $attributeId = $assignment['descriptor']['fields']['id'];
                $entry = [
                    ...$assignment['descriptor']['fields'],
                    'is_required' => $assignment['is_required'],
                    'sort_order' => $assignment['sort_order'],
                    'inherited' => ! $isOwn,
                    'context' => $context->value,
                    'options' => $assignment['descriptor']['options'],
                ];

                if (isset($index[$attributeId])) {
                    $ordered[$index[$attributeId]] = $entry;
                } else {
                    $ordered[] = $entry;
                    $index[$attributeId] = array_key_last($ordered);
                }
            }
        }

        return collect(array_values($ordered));
    }

    /**
     * An assignment read through the `attributes` relation (pivot loaded).
     *
     * @return array{descriptor: array{fields: array<string, mixed>, options: array<int, array<string, mixed>>}, is_required: bool, sort_order: int}
     */
    public static function assignmentFromPivot(Attribute $attribute): array
    {
        return [
            'descriptor' => self::describe($attribute),
            'is_required' => (bool) $attribute->pivot->is_required,
            'sort_order' => (int) $attribute->pivot->sort_order,
        ];
    }

    /**
     * The category-independent half of an entry. Computed ONCE per attribute
     * by the batched read, which reuses it across every category assigning it.
     *
     * @return array{fields: array<string, mixed>, options: array<int, array<string, mixed>>}
     */
    public static function describe(Attribute $attribute): array
    {
        return [
            'fields' => [
                'id' => $attribute->id,
                'code' => $attribute->code,
                'name' => $attribute->name,
                'type' => $attribute->type,
                'description' => $attribute->description,
                'help_text' => $attribute->help_text,
                'placeholder' => $attribute->placeholder,
                'icon' => $attribute->icon,
                'config' => $attribute->config,
                'relation_target' => $attribute->relation_target,
            ],
            'options' => self::optionsFor($attribute),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, color: ?string, icon: ?string, sort_order: int, is_default: bool}>
     */
    private static function optionsFor(Attribute $attribute): array
    {
        if ($attribute->type !== 'enum') {
            return [];
        }

        return $attribute->options->map(static fn ($option): array => [
            'value' => $option->value,
            'label' => $option->label,
            'color' => $option->color,
            'icon' => $option->icon,
            'sort_order' => $option->sort_order,
            'is_default' => $option->is_default,
        ])->all();
    }
}
