<?php

declare(strict_types=1);

use App\Models\DocumentLayout;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Reward;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

if (! function_exists('offerProjectionFullQuote')) {
    /**
     * Spec 0331: an offer carrying every value the Contract/WorkOrder detail
     * projects read-only (referent with type, source, a product line,
     * commercial, reporter, layout, payment method, a reward and two account
     * managers at positions 1 and 2).
     */
    function offerProjectionFullQuote(): Quote
    {
        $referent = Referent::factory()->create(['referent_type_id' => ReferentType::factory()->create()->id]);
        $opportunity = Opportunity::factory()->create([
            'referent_id' => $referent->id,
        ]);
        OpportunityProductLine::factory()->create(['opportunity_id' => $opportunity->id]);

        $quote = Quote::factory()->create([
            'opportunity_id' => $opportunity->id,
            // REQUIREMENT CHANGED: spec 0332 — the Fonte is the offer's own.
            'source_id' => Source::factory()->create()->id,
            'commercial_id' => Referent::factory()->create()->id,
            'reporter_id' => Referent::factory()->create()->id,
            'layout_id' => DocumentLayout::factory()->create()->id,
            'payment_method_id' => PaymentMethod::factory()->create()->id,
        ]);

        Reward::factory()->create([
            'source_type' => Relation::getMorphAlias(Quote::class),
            'source_id' => $quote->id,
        ]);
        $quote->managers()->attach([
            User::factory()->create()->id => ['position' => 2],
            User::factory()->create()->id => ['position' => 1],
        ]);

        return $quote->fresh();
    }
}
