<?php

use App\DataObjects\Commissions\CommissionRecipient;
use App\DataObjects\Commissions\CommissionResolutionContext;
use App\DataObjects\Commissions\ResolvedCommissionRule;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\Referent;
use App\Models\User;
use App\Services\Commissions\CommissionRuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0090 D-5/D-6/D-7: a referent and the user declared to BE it
 * (`referents.user_id`) form a single IDENTITY SET for rule matching — a
 * rule intestata a either identity applies (AC-010, AC-012), never to a
 * DIFFERENT person even absent the link (AC-013, AC-014), and the tie-break
 * inside a gradino still decides on priority/valid_from/id, not on identity
 * (AC-015). AC-011 (D-8/INV-1, the persisted snapshot) is exercised on the
 * real write path in tests/Feature/Quotes/QuoteCommissionRecipientIdentityTest.php.
 */
uses(RefreshDatabase::class);

function matchForRole(Product $product, CommissionRecipientRole $role, CommissionRecipient $recipient): ?ResolvedCommissionRule
{
    $matches = app(CommissionRuleResolver::class)->resolve(new CommissionResolutionContext(
        productId: $product->id,
        productCategoryId: $product->category_id,
        roles: [$role],
        referenceDate: new DateTimeImmutable('now'),
        recipients: [$role->value => $recipient],
    ));

    return $matches[0] ?? null;
}

it('lets a rule intestata to the linked USER win for the referent actually on the quote (AC-010)', function () {
    $linkedUser = User::factory()->create();
    $commercial = Referent::factory()->create(['user_id' => $linkedUser->id]);
    $product = Product::factory()->create();

    $personalOnUser = CommissionConfiguration::factory()
        ->forRecipient('user', $linkedUser->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => $product->id]);
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
    ]);

    $match = matchForRole($product, CommissionRecipientRole::Commercial, new CommissionRecipient('referent', $commercial->id));

    expect($match->configurationId)->toBe($personalOnUser->id)
        ->and($match->origin)->toBe(CommissionOrigin::Recipient);
});

it('works symmetrically: a rule on the linked REFERENT wins for the SUPERVISOR user actually on the quote (AC-012)', function () {
    $supervisorUser = User::factory()->create();
    Referent::factory()->create(['user_id' => $supervisorUser->id]);
    $linkedReferent = Referent::query()->where('user_id', $supervisorUser->id)->sole();
    $product = Product::factory()->create();

    $personalOnReferent = CommissionConfiguration::factory()
        ->forRecipient('referent', $linkedReferent->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Supervisor, 'product_id' => $product->id]);

    $match = matchForRole($product, CommissionRecipientRole::Supervisor, new CommissionRecipient('user', $supervisorUser->id));

    expect($match->configurationId)->toBe($personalOnReferent->id);
});

it('never applies a rule on a user when the referent on the quote is NOT linked to anyone (AC-013, INV-2)', function () {
    $unlinkedCommercial = Referent::factory()->create(['user_id' => null]);
    $someUser = User::factory()->create();
    $product = Product::factory()->create();

    CommissionConfiguration::factory()
        ->forRecipient('user', $someUser->id)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial]);

    $match = matchForRole($product, CommissionRecipientRole::Commercial, new CommissionRecipient('referent', $unlinkedCommercial->id));

    expect($match)->toBeNull();
});

it('never applies a rule on a user linked to a DIFFERENT referent (AC-014, INV-2)', function () {
    $otherUser = User::factory()->create();
    Referent::factory()->create(['user_id' => $otherUser->id]);
    $commercial = Referent::factory()->create(['user_id' => null]);
    $product = Product::factory()->create();

    CommissionConfiguration::factory()
        ->forRecipient('user', $otherUser->id)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial]);

    $match = matchForRole($product, CommissionRecipientRole::Commercial, new CommissionRecipient('referent', $commercial->id));

    expect($match)->toBeNull();
});

it('breaks a tie between the referent rule and its linked user rule by priority, not by identity (AC-015)', function () {
    $linkedUser = User::factory()->create();
    $commercial = Referent::factory()->create(['user_id' => $linkedUser->id]);
    $product = Product::factory()->create();

    CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => $product->id, 'priority' => 5]);
    $higherPriorityOnUser = CommissionConfiguration::factory()
        ->forRecipient('user', $linkedUser->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => $product->id, 'priority' => 50]);

    $match = matchForRole($product, CommissionRecipientRole::Commercial, new CommissionRecipient('referent', $commercial->id));

    expect($match->configurationId)->toBe($higherPriorityOnUser->id);
});

it('still runs exactly 5 resolution queries per role with a linked identity present and no rule matching either (AC-016, D-6)', function () {
    $linkedUser = User::factory()->create();
    $commercial = Referent::factory()->create(['user_id' => $linkedUser->id]);
    $product = Product::factory()->create();

    DB::enableQueryLog();
    matchForRole($product, CommissionRecipientRole::Commercial, new CommissionRecipient('referent', $commercial->id));
    $queries = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'commission_configurations'));

    expect($queries)->toHaveCount(5);
});

it('keeps resolving exactly like before when referents.user_id is null everywhere (AC-017, INV-5, D-11)', function () {
    $commercial = Referent::factory()->create(['user_id' => null]);
    $product = Product::factory()->create();

    $personal = CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => $product->id]);

    $match = matchForRole($product, CommissionRecipientRole::Commercial, new CommissionRecipient('referent', $commercial->id));

    expect($match->configurationId)->toBe($personal->id);
});
