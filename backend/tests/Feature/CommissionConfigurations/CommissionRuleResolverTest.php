<?php

use App\DataObjects\Commissions\CommissionResolutionContext;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
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
