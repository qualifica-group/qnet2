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
use App\Models\Registry;
use App\Services\Commissions\CommissionRuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0089 D-4: the 5-step chain (personal product/category/recipient-wide,
 * THEN role product/category), and INV-3 (a personal rule never leaks to any
 * other recipient). tests/Feature/CommissionConfigurations/CommissionRuleResolverTest.php
 * is left untouched — its role-only scenarios (recipients: []) keep proving
 * AC-019/D-10 with zero modification.
 */
uses(RefreshDatabase::class);

function resolveOneRole(
    Product $product,
    CommissionRecipient $recipient,
    ?string $referenceDate = null,
): ?ResolvedCommissionRule {
    // Defaults to "now" (not a fixed past date) so it always lands on/after
    // the factory's own default `valid_from` (`now()->startOfDay()`).
    $matches = app(CommissionRuleResolver::class)->resolve(new CommissionResolutionContext(
        productId: $product->id,
        productCategoryId: $product->category_id,
        roles: [CommissionRecipientRole::Commercial],
        referenceDate: new DateTimeImmutable($referenceDate ?? 'now'),
        recipients: [CommissionRecipientRole::Commercial->value => $recipient],
    ));

    return $matches[0] ?? null;
}

it('lets a personal PRODUCT rule beat a role PRODUCT rule for the same recipient (AC-002)', function () {
    $commercial = Referent::factory()->create();
    $product = Product::factory()->create();

    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'value' => 5,
    ]);
    $personal = CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => $product->id, 'value' => 12]);

    $match = resolveOneRole($product, new CommissionRecipient('referent', $commercial->id));

    expect($match->configurationId)->toBe($personal->id)
        ->and($match->origin)->toBe(CommissionOrigin::Recipient);
});

it('lets a personal CATEGORY rule beat a role PRODUCT rule — the recipient dimension dominates product specificity (AC-003)', function () {
    $commercial = Referent::factory()->create();
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
    ]);
    $personalCategory = CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::ProductCategory)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_category_id' => $category->id]);

    $match = resolveOneRole($product, new CommissionRecipient('referent', $commercial->id));

    expect($match->configurationId)->toBe($personalCategory->id);
});

it('orders the same recipient product over category over the recipient-wide RECIPIENT scope (AC-004)', function () {
    $commercial = Referent::factory()->create();
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    $recipientWide = CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial]);
    $personalCategory = CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::ProductCategory)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_category_id' => $category->id]);

    $matchAgainstCategoryAndRecipient = resolveOneRole($product, new CommissionRecipient('referent', $commercial->id));
    expect($matchAgainstCategoryAndRecipient->configurationId)->toBe($personalCategory->id);

    $personalCategory->delete();
    $matchAgainstRecipientOnly = resolveOneRole($product, new CommissionRecipient('referent', $commercial->id));
    expect($matchAgainstRecipientOnly->configurationId)->toBe($recipientWide->id);
});

it('never applies a personal rule intestated to a DIFFERENT recipient, even absent any role rule (AC-005, INV-3)', function () {
    $commercial = Referent::factory()->create();
    $stranger = Referent::factory()->create();
    $product = Product::factory()->create();

    CommissionConfiguration::factory()
        ->forRecipient('referent', $stranger->id)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial]);

    $match = resolveOneRole($product, new CommissionRecipient('referent', $commercial->id));

    expect($match)->toBeNull();
});

it('falls back to the role gradini when the personal rule is suspended or out of validity (AC-006)', function () {
    $commercial = Referent::factory()->create();
    $product = Product::factory()->create();

    CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::Product)
        ->create([
            'recipient_role' => CommissionRecipientRole::Commercial,
            'product_id' => $product->id,
            'status' => CommissionConfigurationStatus::Suspended,
        ]);
    $roleRule = CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
    ]);

    $match = resolveOneRole($product, new CommissionRecipient('referent', $commercial->id));

    expect($match->configurationId)->toBe($roleRule->id)
        ->and($match->origin)->toBe(CommissionOrigin::Product);
});

it('resolves every role independently — a personal SUPPLIER rule never influences the COMMERCIAL role (AC-007)', function () {
    $commercial = Referent::factory()->create();
    $supplier = Registry::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);

    CommissionConfiguration::factory()
        ->forRecipient('registry', $supplier->id)
        ->create(['recipient_role' => CommissionRecipientRole::Supplier, 'value' => 99]);
    $commercialRoleRule = CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
    ]);

    $matches = app(CommissionRuleResolver::class)->resolve(new CommissionResolutionContext(
        productId: $product->id,
        productCategoryId: $product->category_id,
        roles: [CommissionRecipientRole::Commercial, CommissionRecipientRole::Supplier],
        referenceDate: new DateTimeImmutable('now'),
        recipients: [
            CommissionRecipientRole::Commercial->value => new CommissionRecipient('referent', $commercial->id),
            CommissionRecipientRole::Supplier->value => new CommissionRecipient('registry', $supplier->id),
        ],
    ));

    $byRole = collect($matches)->keyBy(fn ($match) => $match->role->value);
    expect($byRole['COMMERCIAL']->configurationId)->toBe($commercialRoleRule->id)
        ->and($byRole['SUPPLIER']->origin)->toBe(CommissionOrigin::Recipient);
});

it('keeps the priority/valid_from/id tie-break inside a single gradino (personal PRODUCT)', function () {
    $commercial = Referent::factory()->create();
    $product = Product::factory()->create();

    CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => $product->id, 'priority' => 5]);
    $winner = CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => $product->id, 'priority' => 50]);

    $match = resolveOneRole($product, new CommissionRecipient('referent', $commercial->id));

    expect($match->configurationId)->toBe($winner->id);
});

it('never runs more than the 5 gradino queries for a role with an unmatched recipient (R-1 sanity)', function () {
    $commercial = Referent::factory()->create();
    $product = Product::factory()->create();

    DB::enableQueryLog();
    resolveOneRole($product, new CommissionRecipient('referent', $commercial->id));
    $queries = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'commission_configurations'));

    expect($queries)->toHaveCount(5);
});
