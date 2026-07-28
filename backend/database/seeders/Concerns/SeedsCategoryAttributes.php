<?php

namespace Database\Seeders\Concerns;

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\ProductCategory;

/**
 * Creates catalogue attributes and assigns them to a category in one usage
 * context (spec 0061). Shared by the seeders that provision the client's
 * reference attributes — the Product-context catalogue (QualificaCatalogSeeder)
 * and the Opportunity-context one (QualificaContactProcessingSeeder) — so the
 * "natural key + additive assignment" rules below live in ONE place.
 *
 * `code` is the natural key: an attribute already in the catalogue keeps its
 * label/type, so both a manual rename and a row imported from q-crm survive a
 * re-seed untouched. Assignments and options are ADDITIVE (unlike the
 * Services' full-replace sync), so a re-seed never wipes what was configured
 * by hand.
 */
trait SeedsCategoryAttributes
{
    /**
     * @param  list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, relation_target?: array<string, mixed>}>  $specs
     */
    protected function seedCategoryAttributes(ProductCategory $category, array $specs, AttributeContext $context): void
    {
        foreach ($specs as $spec) {
            $attribute = Attribute::firstOrCreate(
                ['code' => $spec['code']],
                [
                    'name' => $spec['name'],
                    'type' => $spec['type'],
                    // Required by, and only meaningful for, the `relation` type.
                    'relation_target' => $spec['relation_target'] ?? null,
                ],
            );

            $this->seedAttributeOptions($attribute, $spec['options'] ?? []);
            $this->assignAttribute($category, $attribute, $context);
        }
    }

    /**
     * The discrete value list of an ENUM attribute. Seeded here rather than
     * through AttributeService because that Service's nested full-replace
     * would drop the options added by hand from the attributes module.
     *
     * @param  list<array{value: string, label: string}>  $options
     */
    protected function seedAttributeOptions(Attribute $attribute, array $options): void
    {
        $sortOrder = 0;

        foreach ($options as $option) {
            AttributeOption::firstOrCreate(
                ['attribute_id' => $attribute->id, 'value' => $option['value']],
                ['label' => $option['label'], 'sort_order' => $sortOrder],
            );

            $sortOrder++;
        }
    }

    /**
     * Additive on purpose, unlike ProductCategoryService::syncAttributes()
     * which is a full replace: a re-seed must not wipe the assignments made
     * by hand from the category configurator.
     */
    protected function assignAttribute(ProductCategory $category, Attribute $attribute, AttributeContext $context): void
    {
        $isAssigned = $category->attributes()
            ->wherePivot('context', $context->value)
            ->where('attributes.id', $attribute->id)
            ->exists();

        if ($isAssigned) {
            return;
        }

        $category->attributes()->attach($attribute->id, [
            'context' => $context->value,
            'is_required' => false,
            'sort_order' => 0,
        ]);
    }
}
