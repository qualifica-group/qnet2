<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use Database\Seeders\DemoCatalog\DemoProductCatalogue;
use Database\Seeders\DemoProductCategorySeeder;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The demo product catalogue: the tree, its attributes in both usage contexts
// (spec 0061), the form sections that group them (spec 0062) and the offer
// filed under it (spec 0017).
uses(RefreshDatabase::class);

function seedDemoCatalog(): void
{
    foreach (DemoCategoryCatalogue::TREE as $branch) {
        BusinessFunction::query()->firstOrCreate(['name' => $branch['business_function']]);
    }

    test()->seed(DemoProductCategorySeeder::class);
    test()->seed(DemoProductSeeder::class);
}

it('seeds the demo tree with the branch business function on the root only', function (): void {
    seedDemoCatalog();

    expect(ProductCategory::query()->count())->toBe(count(DemoCategoryCatalogue::categoryNames()));

    foreach (DemoCategoryCatalogue::TREE as $rootName => $branch) {
        $root = ProductCategory::query()->where('name', $rootName)->firstOrFail();
        $functionId = BusinessFunction::query()->where('name', $branch['business_function'])->value('id');

        expect($root->parent_id)->toBeNull()
            ->and($root->business_function_id)->toBe($functionId);

        foreach ($branch['children'] as $childName) {
            $child = ProductCategory::query()->where('name', $childName)->firstOrFail();

            // The child inherits the function, it never owns one (spec 0023).
            expect($child->parent_id)->toBe($root->id)
                ->and($child->business_function_id)->toBeNull();
        }
    }
});

it('makes every leaf resolve an effective business function and its own products', function (): void {
    seedDemoCatalog();

    $hierarchy = app(CategoryHierarchy::class);
    $summaries = $hierarchy->effectiveBusinessFunctionSummaries();

    foreach (DemoProductCatalogue::PRODUCTS as $categoryName => $products) {
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

        // Both halves of a valid `product_lines` row plus the products the
        // mandatory `products_of_interest` is picked from.
        expect($summaries[$category->id])->not->toBeNull($categoryName)
            ->and(Product::query()->where('category_id', $category->id)->count())->toBe(count($products), $categoryName);
    }
});

it('assigns the demo attributes in the Product and Quote usage contexts and lets the branch inherit them', function (): void {
    seedDemoCatalog();

    $hierarchy = app(CategoryHierarchy::class);
    $online = ProductCategory::query()->where('name', 'Corsi Online')->firstOrFail();
    $classroom = ProductCategory::query()->where('name', 'Corsi in Aula')->firstOrFail();

    $onlineProductCodes = $hierarchy->effectiveAttributes($online, AttributeContext::Product)->pluck('code')->all();
    $classroomProductCodes = $hierarchy->effectiveAttributes($classroom, AttributeContext::Product)->pluck('code')->all();
    $onlineQuoteCodes = $hierarchy->effectiveAttributes($online, AttributeContext::Quote)->pluck('code')->all();

    expect($onlineProductCodes)
        // Inherited from the branch root...
        ->toContain('demo_course_hours')
        // ...plus the leaf's own.
        ->toContain('demo_platform')
        ->and($classroomProductCodes)
        ->toContain('demo_course_hours')
        ->not->toContain('demo_platform')
        // The quote context is a separate set on the same categories.
        ->and($onlineQuoteCodes)->toContain('demo_enrollment_status')
        ->and($onlineProductCodes)->not->toContain('demo_enrollment_status');
});

it('shares one catalogue row when two branches assign the same attribute code', function (): void {
    seedDemoCatalog();

    $notes = Attribute::query()->where('code', 'demo_processing_notes')->get();

    expect($notes)->toHaveCount(1)
        ->and($notes->first()->categories()->wherePivot('context', AttributeContext::Quote->value)->count())->toBe(2);
});

it('writes one layout row per category per context, keeping only the codes it resolves', function (): void {
    seedDemoCatalog();

    $categoryNames = DemoCategoryCatalogue::categoryNames();

    expect(AttributeLayout::query()->where('context', AttributeContext::Product->value)->count())->toBe(count($categoryNames))
        ->and(AttributeLayout::query()->where('context', AttributeContext::Quote->value)->count())->toBe(count($categoryNames));

    $sectionIdsFor = function (string $categoryName, AttributeContext $context): array {
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();
        $layout = app(AttributeLayoutService::class)
            ->resolveExact($category, $context, LayoutFormScope::All);

        return array_column($layout['sections'], 'id');
    };

    // The delivery section holds `demo_platform` alone: only the online leaf
    // resolves it, so the section is dropped everywhere else.
    expect($sectionIdsFor('Corsi Online', AttributeContext::Product))->toBe(['demo-course-data', 'demo-course-delivery'])
        ->and($sectionIdsFor('Corsi in Aula', AttributeContext::Product))->toBe(['demo-course-data'])
        ->and($sectionIdsFor('Consulenza IT', AttributeContext::Quote))->toBe(['demo-appointment', 'demo-consulting-outcome']);
});

it('is idempotent and never overwrites a layout configured by hand', function (): void {
    seedDemoCatalog();

    $category = ProductCategory::query()->where('name', 'Corsi in Aula')->firstOrFail();
    AttributeLayout::query()
        ->where('product_category_id', $category->id)
        ->where('context', AttributeContext::Product->value)
        ->update(['layout' => ['sections' => []]]);

    seedDemoCatalog();

    expect(ProductCategory::query()->count())->toBe(count(DemoCategoryCatalogue::categoryNames()))
        ->and(Product::query()->count())->toBe(collect(DemoProductCatalogue::PRODUCTS)->flatten(1)->count())
        ->and(Attribute::query()->where('code', 'demo_course_hours')->count())->toBe(1)
        // Left exactly as the manual edit left it.
        ->and(AttributeLayout::query()
            ->where('product_category_id', $category->id)
            ->where('context', AttributeContext::Product->value)
            ->value('layout'))->toBe(['sections' => []]);
});

it('stores the catalogue attribute values on every seeded product', function (): void {
    seedDemoCatalog();

    $product = Product::query()->where('name', 'Corso Digital Marketing Online')->firstOrFail();

    expect($product->attribute_values)->toMatchArray([
        'demo_course_hours' => 20,
        'demo_platform' => 'teams',
    ]);
});
