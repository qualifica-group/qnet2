<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Referent;
use App\Models\User;
use App\Services\Commissions\QuoteLineCommissionWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Spec 0090 D-8/INV-1 (AC-011) — the single most fragile point of the spec:
 * even when the WINNING rule is intestata to the linked user, the persisted
 * snapshot on `quote_line_commissions` stays intestato to the REFERENT
 * actually on the Quote, never to the rule's own target. Exercises the real
 * write path — `QuoteLineCommissionWriter` -> `QuoteCommissionInitializer` ->
 * `CommissionRuleResolver` -> `CommissionRecipientIdentities` — not a mock
 * of any of them.
 */
uses(RefreshDatabase::class);

it('persists the snapshot intestato to the referent on the quote, never to the linked user the winning rule targets (AC-011, D-8, INV-1)', function () {
    $linkedUser = User::factory()->create();
    $commercial = Referent::factory()->create(['user_id' => $linkedUser->id]);
    $product = Product::factory()->create();

    CommissionConfiguration::factory()
        ->forRecipient('user', $linkedUser->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => $product->id, 'value' => 12]);

    $quote = Quote::factory()->create(['commercial_id' => $commercial->id]);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'net_amount' => 100,
    ]);

    app(QuoteLineCommissionWriter::class)->sync($line, null, regenerate: true);

    $commission = $line->commissions()->where('recipient_role', CommissionRecipientRole::Commercial)->sole();

    expect($commission->recipient_type)->toBe('referent')
        ->and($commission->recipient_id)->toBe($commercial->id)
        ->and($commission->origin)->toBe(CommissionOrigin::Recipient);
});
