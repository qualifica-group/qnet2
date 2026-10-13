<?php

use App\DataObjects\Commissions\CommissionRecipient;
use App\DataObjects\Commissions\CommissionResolutionContext;
use App\DataObjects\Commissions\ResolvedCommissionRule;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Services\Commissions\CommissionRuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves product before category and applies priority and validity deterministically', function () {
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::ProductCategory,
        'product_category_id' => $category->id,
        'product_id' => null,
        'priority' => 999,
        'valid_from' => '2026-01-01',
        'status' => CommissionConfigurationStatus::Active,
    ]);
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_category_id' => null,
        'product_id' => $product->id,
        'priority' => 10,
        'valid_from' => '2026-01-01',
        'status' => CommissionConfigurationStatus::Active,
    ]);
    $winner = CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_category_id' => null,
        'product_id' => $product->id,
        'priority' => 20,
        'valid_from' => '2026-02-01',
        'status' => CommissionConfigurationStatus::Active,
    ]);
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_category_id' => null,
        'product_id' => $product->id,
        'priority' => 100,
        'valid_from' => '2027-01-01',
        'status' => CommissionConfigurationStatus::Active,
    ]);

    $matches = app(CommissionRuleResolver::class)->resolve(new CommissionResolutionContext(
        productId: $product->id,
        productCategoryId: $category->id,
        roles: [CommissionRecipientRole::Commercial, CommissionRecipientRole::Supplier],
        referenceDate: new DateTimeImmutable('2026-07-29'),
    ));

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->configurationId)->toBe($winner->id)
        ->and($matches[0]->role)->toBe(CommissionRecipientRole::Commercial);
});

it('breaks equal-priority ties by latest valid_from and then highest id', function () {
    $product = Product::factory()->create();
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'priority' => 10,
        'valid_from' => '2026-01-01',
    ]);
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'priority' => 10,
        'valid_from' => '2026-02-01',
    ]);
    $highestId = CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'priority' => 10,
        'valid_from' => '2026-02-01',
    ]);

    $matches = app(CommissionRuleResolver::class)->resolve(new CommissionResolutionContext(
        productId: $product->id,
        productCategoryId: $product->category_id,
        roles: [CommissionRecipientRole::Commercial],
        referenceDate: new DateTimeImmutable('2026-07-29'),
    ));

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->configurationId)->toBe($highestId->id);
});

it('ignores suspended, expired and future rules and returns no match when none remains', function () {
    $product = Product::factory()->create();
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'status' => CommissionConfigurationStatus::Suspended,
        'valid_from' => '2026-01-01',
    ]);
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'valid_from' => '2026-01-01',
        'valid_until' => '2026-07-28',
    ]);
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'valid_from' => '2026-07-30',
    ]);

    $matches = app(CommissionRuleResolver::class)->resolve(new CommissionResolutionContext(
        productId: $product->id,
        productCategoryId: $product->category_id,
        roles: [CommissionRecipientRole::Commercial, CommissionRecipientRole::Supplier],
        referenceDate: new DateTimeImmutable('2026-07-29'),
    ));

    expect($matches)->toBe([]);
});

// ---------------------------------------------------------------------------
// Spec 0223 — a category rule covers the whole branch, the closest category wins
// ---------------------------------------------------------------------------

/**
 * @return array{grandparent: ProductCategory, parent: ProductCategory, category: ProductCategory, product: Product}
 */
function branchCommissionTree(): array
{
    $grandparent = ProductCategory::factory()->create(['is_selectable' => false]);
    $parent = ProductCategory::factory()->create(['parent_id' => $grandparent->id, 'is_selectable' => false]);
    $category = ProductCategory::factory()->create(['parent_id' => $parent->id]);

    return [
        'grandparent' => $grandparent,
        'parent' => $parent,
        'category' => $category,
        'product' => Product::factory()->create(['category_id' => $category->id]),
    ];
}

function branchCategoryRule(ProductCategory $category, int $priority = 0): CommissionConfiguration
{
    return CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::ProductCategory,
        'product_category_id' => $category->id,
        'product_id' => null,
        'priority' => $priority,
        'valid_from' => '2026-01-01',
        'status' => CommissionConfigurationStatus::Active,
    ]);
}

function resolveBranchRule(Product $product, array $recipients = []): ?ResolvedCommissionRule
{
    $matches = app(CommissionRuleResolver::class)->resolve(new CommissionResolutionContext(
        productId: $product->id,
        productCategoryId: $product->category_id,
        roles: [CommissionRecipientRole::Commercial],
        referenceDate: new DateTimeImmutable('2026-07-29'),
        recipients: $recipients,
    ));

    return $matches[0] ?? null;
}

it('AC-001: applies a rule on an ancestor category to the products of its branch', function () {
    ['parent' => $parent, 'product' => $product] = branchCommissionTree();
    $rule = branchCategoryRule($parent);

    $match = resolveBranchRule($product);

    expect($match?->configurationId)->toBe($rule->id)
        ->and($match->origin)->toBe(CommissionOrigin::ProductCategory);
});

it('AC-002: the closest category wins over a higher priority further up the branch', function () {
    ['grandparent' => $grandparent, 'parent' => $parent, 'category' => $category, 'product' => $product] = branchCommissionTree();
    branchCategoryRule($grandparent, priority: 9);
    $onParent = branchCategoryRule($parent, priority: 5);
    $onCategory = branchCategoryRule($category, priority: 1);

    expect(resolveBranchRule($product)?->configurationId)->toBe($onCategory->id);

    $onCategory->delete();

    expect(resolveBranchRule($product)?->configurationId)->toBe($onParent->id);
});

it('AC-003: on the same category the priority, then valid_from, then id still decide', function () {
    ['parent' => $parent, 'product' => $product] = branchCommissionTree();
    branchCategoryRule($parent, priority: 1);
    $winner = branchCategoryRule($parent, priority: 7);

    expect(resolveBranchRule($product)?->configurationId)->toBe($winner->id);
});

it('AC-004: a product rule beats an ancestor rule, and a personal ancestor rule beats a role rule on the category', function () {
    ['parent' => $parent, 'category' => $category, 'product' => $product] = branchCommissionTree();
    branchCategoryRule($parent, priority: 99);
    $productRule = CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_category_id' => null,
        'product_id' => $product->id,
        'valid_from' => '2026-01-01',
        'status' => CommissionConfigurationStatus::Active,
    ]);

    expect(resolveBranchRule($product)?->configurationId)->toBe($productRule->id);

    $productRule->delete();
    branchCategoryRule($category, priority: 50);
    $commercial = Referent::factory()->create();
    $personal = CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::ProductCategory)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_category_id' => $parent->id, 'valid_from' => '2026-01-01']);

    $match = resolveBranchRule($product, [CommissionRecipientRole::Commercial->value => new CommissionRecipient('referent', $commercial->id)]);

    expect($match?->configurationId)->toBe($personal->id)
        ->and($match->origin)->toBe(CommissionOrigin::Recipient);
});

it('AC-005: a rule on a sibling or a child category never applies', function () {
    ['parent' => $parent, 'category' => $category, 'product' => $product] = branchCommissionTree();
    branchCategoryRule(ProductCategory::factory()->create(['parent_id' => $parent->id]));
    branchCategoryRule(ProductCategory::factory()->create(['parent_id' => $category->id]));

    expect(resolveBranchRule($product))->toBeNull();
});
