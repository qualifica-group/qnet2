<?php

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Integrations\Formalab\Models\FormalabCategorySetting;
use Integrations\Formalab\Seeders\LegacyCategoriesSeeder;
use Integrations\Formalab\Services\CategoryFieldMapResolver;

uses(RefreshDatabase::class);

/** A legacy category (by old_id) carrying the course fields in the work order context. */
function formalabLegacyCategory(int $legacyId, ?ProductCategory $parent = null, bool $courseFields = true): ProductCategory
{
    $category = ProductCategory::factory()->create(['parent_id' => $parent?->id]);
    $category->forceFill(['old_id' => $legacyId])->save();
    $position = 0;

    if ($courseFields) {
        foreach (formalabCourseAttributes() as $attribute) {
            $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => $position++, 'context' => AttributeContext::WorkOrder->value]);
        }
    }

    return $category;
}

it('links the branch roots of the legacy training categories and maps their course fields', function () {
    $trainingRoot = ProductCategory::factory()->create();
    $classesGroup = ProductCategory::factory()->create(['parent_id' => $trainingRoot->id]);
    $classes = formalabLegacyCategory(142, $classesGroup);
    $projects = formalabLegacyCategory(74);
    $sibling = formalabLegacyCategory(999, $classesGroup);

    $this->seed(LegacyCategoriesSeeder::class);

    $resolver = app(CategoryFieldMapResolver::class);
    $classesSettings = $resolver->forCategory($classes);
    expect($classesSettings['enabled'])->toBeTrue()
        ->and($classesSettings['field_map'])->toBe(collect(formalabSuggestedMap())->pluck('attribute_code', 'formalab_field')->all())
        ->and($classesSettings['field_map_owner']->id)->toBe($classes->id)
        ->and($resolver->forCategory($projects)['field_map'])->toHaveCount(12)
        ->and(FormalabCategorySetting::query()->where('product_category_id', $trainingRoot->id)->value('enabled'))->toBeTrue()
        // A category the legacy did not use for Formalab is under an enabled root but has no map
        ->and($resolver->forCategory($sibling)['field_map'])->toBe([])
        ->and($resolver->forCategory($sibling)['enabled'])->toBeTrue();
});

it('keeps a map configured by hand, skips a category without the course name and is idempotent', function () {
    $classes = formalabLegacyCategory(142);
    $bare = formalabLegacyCategory(189, courseFields: false);
    formalabLinkCategory($classes, [['formalab_field' => 'label', 'attribute_code' => 'gol_notice']], enabled: false);

    $this->seed(LegacyCategoriesSeeder::class);
    $this->seed(LegacyCategoriesSeeder::class);

    $resolver = app(CategoryFieldMapResolver::class);
    expect($resolver->forCategory($classes))->toMatchArray(['enabled' => true, 'field_map' => ['label' => 'gol_notice']])
        ->and(FormalabCategorySetting::query()->where('product_category_id', $bare->id)->value('field_map'))->toBeNull()
        ->and(FormalabCategorySetting::query()->count())->toBe(2);
});
