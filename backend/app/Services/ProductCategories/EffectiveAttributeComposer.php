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
 * the override rule and the row shape. Stateless: static, so CategoryHierarchy
 * keeps its argument-less construction.
 */
final class EffectiveAttributeComposer
{
    /**
     * Walks $chain root→self: the MOST SPECIFIC assignment of an attribute
     * wins, but keeps the position where the attribute FIRST appeared.
     *
     * @param  Collection<int, ProductCategory>  $chain  root-first, ending with $category
     * @param  callable(ProductCategory): iterable<int, Attribute>  $ownRows  a level's own assignments in $context, pivot loaded
     * @return Collection<int, array<string, mixed>>
     */
    public static function compose(ProductCategory $category, Collection $chain, AttributeContext $context, callable $ownRows): Collection
    {
        $ordered = [];
        $index = [];

        foreach ($chain as $level) {
            $isOwn = $level->is($category);

            foreach ($ownRows($level) as $attribute) {
                $entry = self::entry($attribute, ! $isOwn, $context);

                if (isset($index[$attribute->id])) {
                    $ordered[$index[$attribute->id]] = $entry;
                } else {
                    $ordered[] = $entry;
                    $index[$attribute->id] = array_key_last($ordered);
                }
            }
        }

        return collect(array_values($ordered));
    }

    /**
     * @return array<string, mixed>
     */
    private static function entry(Attribute $attribute, bool $inherited, AttributeContext $context): array
    {
        return [
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
            'is_required' => (bool) $attribute->pivot->is_required,
            'sort_order' => (int) $attribute->pivot->sort_order,
            'inherited' => $inherited,
            'context' => $context->value,
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
