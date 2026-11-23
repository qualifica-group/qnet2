<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Integrations\Formalab\Models\FormalabCategorySetting;
use Integrations\Formalab\Seeders\TrainingCategoriesSeeder;
use Integrations\Formalab\Services\CategoryFieldMapResolver;
use Integrations\Formalab\Support\FormalabField;

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

    $this->seed(TrainingCategoriesSeeder::class);

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

it('gives the current Formazione root the course fields, links and maps it for all its subcategories', function () {
    formalabCourseAttributes();
    $root = ProductCategory::factory()->create(['name' => TrainingCategoriesSeeder::CURRENT_ROOT_NAME, 'is_legacy' => false]);
    $learnerField = Attribute::factory()->create(['code' => 'cpi', 'type' => 'text']);
    $root->attributes()->attach($learnerField->id, ['is_required' => false, 'sort_order' => 4, 'context' => AttributeContext::WorkOrder->value]);
    $region = ProductCategory::factory()->create(['parent_id' => ProductCategory::factory()->create(['parent_id' => $root->id])->id]);
    $legacyTwin = ProductCategory::factory()->create(['name' => TrainingCategoriesSeeder::CURRENT_ROOT_NAME, 'is_legacy' => true]);

    $this->seed(TrainingCategoriesSeeder::class);
    $this->seed(TrainingCategoriesSeeder::class);

    $codes = DB::table('attribute_category')->join('attributes', 'attributes.id', '=', 'attribute_category.attribute_id')
        ->where('category_id', $root->id)->where('context', AttributeContext::WorkOrder->value)
        ->orderBy('sort_order')->pluck('attributes.code')->all();
    expect($codes)->toBe(['cpi', ...collect(FormalabField::cases())->map->suggestedAttributeCode()->filter()->values()->all()]);

    $resolved = app(CategoryFieldMapResolver::class)->forCategory($region);
    expect($resolved['enabled'])->toBeTrue()
        ->and($resolved['field_map'])->toHaveCount(12)
        ->and($resolved['field_map_owner']->id)->toBe($root->id)
        ->and(FormalabCategorySetting::query()->where('product_category_id', $legacyTwin->id)->exists())->toBeFalse();
});

it('keeps a map configured by hand, skips a category without the course name and is idempotent', function () {
    $classes = formalabLegacyCategory(142);
    $bare = formalabLegacyCategory(189, courseFields: false);
    formalabLinkCategory($classes, [['formalab_field' => 'label', 'attribute_code' => 'gol_notice']], enabled: false);

    $this->seed(TrainingCategoriesSeeder::class);
    $this->seed(TrainingCategoriesSeeder::class);

    $resolver = app(CategoryFieldMapResolver::class);
    expect($resolver->forCategory($classes))->toMatchArray(['enabled' => true, 'field_map' => ['label' => 'gol_notice']])
        ->and(FormalabCategorySetting::query()->where('product_category_id', $bare->id)->value('field_map'))->toBeNull()
        ->and(FormalabCategorySetting::query()->count())->toBe(2);
});
