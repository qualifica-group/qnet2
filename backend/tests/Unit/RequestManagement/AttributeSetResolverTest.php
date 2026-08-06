<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\RequestManagement\AttributeSetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Spec 0084, D-4: AttributeSetResolver generalizes the former Opportunity-only
// ApplicableAttributesResolver (spec 0049) — the UNION (dedup per `code`) of
// the EFFECTIVE attributes of every category in a caller-supplied id list, in
// one usage context. Exercised here directly against category ids (no
// Opportunity/Quote needed): the merge/dedup/sort algorithm is shared by
// every caller (Product, Quote).

uses(TestCase::class, RefreshDatabase::class);

it('returns an empty set for an empty category id list', function (): void {
    $resolved = app(AttributeSetResolver::class)->resolve([], AttributeContext::Quote);

    expect($resolved)->toBeEmpty();
});

it('returns an empty set for categories that carry no attributes', function (): void {
    $category = ProductCategory::factory()->create();

    $resolved = app(AttributeSetResolver::class)->resolve([$category->id], AttributeContext::Quote);

    expect($resolved)->toBeEmpty();
});

it('unions and dedups attributes by code across several categories', function (): void {
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();

    $sharedAttribute = Attribute::factory()->create(['code' => 'shared_field']);
    $onlyA = Attribute::factory()->create(['code' => 'only_a']);
    $onlyB = Attribute::factory()->create(['code' => 'only_b']);

    $categoryA->attributes()->attach($sharedAttribute->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'quote']);
    $categoryA->attributes()->attach($onlyA->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $categoryB->attributes()->attach($sharedAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $categoryB->attributes()->attach($onlyB->id, ['is_required' => false, 'sort_order' => 2, 'context' => 'quote']);

    $resolved = app(AttributeSetResolver::class)->resolve([$categoryA->id, $categoryB->id], AttributeContext::Quote);

    expect($resolved)->toHaveCount(3)
        ->and($resolved->pluck('code')->all())->toEqualCanonicalizing(['shared_field', 'only_a', 'only_b']);
});

it('propagates is_required when the same code is required by at least one category', function (): void {
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();

    $sharedAttribute = Attribute::factory()->create(['code' => 'shared_required']);

    $categoryA->attributes()->attach($sharedAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $categoryB->attributes()->attach($sharedAttribute->id, ['is_required' => true, 'sort_order' => 0, 'context' => 'quote']);

    $resolved = app(AttributeSetResolver::class)->resolve([$categoryA->id, $categoryB->id], AttributeContext::Quote);

    expect($resolved)->toHaveCount(1)
        ->and($resolved->first()->isRequired)->toBeTrue();
});

it('keeps a code non-required when no category requires it', function (): void {
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();

    $sharedAttribute = Attribute::factory()->create(['code' => 'shared_optional']);

    $categoryA->attributes()->attach($sharedAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $categoryB->attributes()->attach($sharedAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    $resolved = app(AttributeSetResolver::class)->resolve([$categoryA->id, $categoryB->id], AttributeContext::Quote);

    expect($resolved->first()->isRequired)->toBeFalse();
});

it('orders the merged set by sort_order then code', function (): void {
    $category = ProductCategory::factory()->create();

    $b = Attribute::factory()->create(['code' => 'b_field']);
    $a = Attribute::factory()->create(['code' => 'a_field']);
    $c = Attribute::factory()->create(['code' => 'c_field']);

    $category->attributes()->attach($b->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'quote']);
    $category->attributes()->attach($a->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'quote']);
    $category->attributes()->attach($c->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    $resolved = app(AttributeSetResolver::class)->resolve([$category->id], AttributeContext::Quote);

    expect($resolved->pluck('code')->all())->toBe(['c_field', 'a_field', 'b_field']);
});

it('does not resolve the same shared category twice when its id repeats (N+1-free)', function (): void {
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'once_only']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    $resolved = app(AttributeSetResolver::class)->resolve([$category->id, $category->id], AttributeContext::Quote);

    expect($resolved)->toHaveCount(1)
        ->and($resolved->first()->code)->toBe('once_only');
});

it('never sees an attribute assigned in a different context', function (): void {
    $category = ProductCategory::factory()->create();
    $productOnly = Attribute::factory()->create(['code' => 'product_only']);
    $category->attributes()->attach($productOnly->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);

    $resolved = app(AttributeSetResolver::class)->resolve([$category->id], AttributeContext::Quote);

    expect($resolved)->toBeEmpty();
});
