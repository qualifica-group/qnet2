<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\RequestManagement\AttributeLayoutMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Spec 0062, layout-contract/opportunity-layout-resolution (AC-008; spec 0084
// D-4 generalized this into AttributeLayoutMerger, exercised here directly
// against category ids): the multi-category merge, server-side, never
// touching the value-pipeline (App\RequestManagement\AttributeSetResolver
// stays the sole authority on which codes are applicable/required).

uses(TestCase::class, RefreshDatabase::class);

it('returns null for an empty category id list', function (): void {
    $resolved = app(AttributeLayoutMerger::class)->resolve([], AttributeContext::Quote, FormMode::Edit);

    expect($resolved)->toBeNull();
});

it('returns null when no contributing category has a configured layout (flat fallback)', function (): void {
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'field_a']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    $resolved = app(AttributeLayoutMerger::class)->resolve([$category->id], AttributeContext::Quote, FormMode::Edit);

    expect($resolved)->toBeNull();
});

it('concatenates two categories\' sections in the caller\'s own order, dedups first-wins, and traps the leftover in "Altre informazioni"', function (): void {
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();

    $shared = Attribute::factory()->create(['code' => 'shared_field']);
    $onlyA = Attribute::factory()->create(['code' => 'only_a']);
    $onlyB = Attribute::factory()->create(['code' => 'only_b']);
    $unplaced = Attribute::factory()->create(['code' => 'unplaced_field']);

    foreach ([$shared, $onlyA, $unplaced] as $index => $attribute) {
        $categoryA->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => $index, 'context' => 'quote']);
    }
    foreach ([$shared, $onlyB] as $index => $attribute) {
        $categoryB->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => $index, 'context' => 'quote']);
    }

    // Category A places `shared_field` and `only_a` — `unplaced_field` is
    // applicable (attached above) but deliberately left OUT of every layout.
    AttributeLayout::factory()->for($categoryA, 'productCategory')
        ->withCodes(['shared_field', 'only_a'], title: 'Section A')
        ->create(['context' => 'quote', 'form_mode' => 'edit']);

    // Category B ALSO places `shared_field` (must be dropped, A already won)
    // plus `only_b`.
    AttributeLayout::factory()->for($categoryB, 'productCategory')
        ->withCodes(['shared_field', 'only_b'], title: 'Section B')
        ->create(['context' => 'quote', 'form_mode' => 'edit']);

    $resolved = app(AttributeLayoutMerger::class)->resolve([$categoryA->id, $categoryB->id], AttributeContext::Quote, FormMode::Edit);

    expect($resolved)->not->toBeNull();
    $sections = $resolved['sections'];

    // Category A's section, then Category B's, then the synthetic trailing one.
    expect($sections)->toHaveCount(3);
    expect($sections[0]['title'])->toBe('Section A');
    expect($sections[1]['title'])->toBe('Section B');
    expect($sections[2]['title'])->toBe('Altre informazioni');

    $placedCodes = collect($sections)
        ->flatMap(fn (array $section): array => collect($section['rows'])->flatMap(fn (array $row): array => $row['items'])->all())
        ->pluck('attribute_code')
        ->all();

    // shared_field appears exactly ONCE (first-wins, from Category A).
    expect($placedCodes)->toEqualCanonicalizing(['shared_field', 'only_a', 'only_b', 'unplaced_field']);
    expect(array_count_values($placedCodes)['shared_field'])->toBe(1);

    // Section A (first-wins) is the one that kept shared_field.
    $sectionACodes = collect($sections[0]['rows'])->flatMap(fn (array $row): array => $row['items'])->pluck('attribute_code')->all();
    expect($sectionACodes)->toBe(['shared_field', 'only_a']);

    // Section B lost shared_field (already placed) — only `only_b` remains.
    $sectionBCodes = collect($sections[1]['rows'])->flatMap(fn (array $row): array => $row['items'])->pluck('attribute_code')->all();
    expect($sectionBCodes)->toBe(['only_b']);

    // The synthetic trailing section holds the un-placed applicable code.
    $trailingCodes = collect($sections[2]['rows'])->flatMap(fn (array $row): array => $row['items'])->pluck('attribute_code')->all();
    expect($trailingCodes)->toBe(['unplaced_field']);
});

it('resolves independently per form_mode — a layout configured for Create does not leak into Edit', function (): void {
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'field_a']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['field_a'])
        ->create(['context' => 'quote', 'form_mode' => 'create']);

    $resolver = app(AttributeLayoutMerger::class);

    expect($resolver->resolve([$category->id], AttributeContext::Quote, FormMode::Create))->not->toBeNull();
    expect($resolver->resolve([$category->id], AttributeContext::Quote, FormMode::Edit))->toBeNull();
});

it('resolves independently per context — a layout configured for Product does not leak into Offerta', function (): void {
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'field_a']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);

    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['field_a'])
        ->create(['context' => 'product', 'form_mode' => 'edit']);

    $resolver = app(AttributeLayoutMerger::class);

    expect($resolver->resolve([$category->id], AttributeContext::Product, FormMode::Edit))->not->toBeNull();
    expect($resolver->resolve([$category->id], AttributeContext::Quote, FormMode::Edit))->toBeNull();
});
