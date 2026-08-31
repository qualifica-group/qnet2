<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuoteManagerWriter;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Spec 0087, D-4: QuoteManagerWriter is the SOLE writer of an Offerta's
// "Gestori Account" — one test per Step (1..4) plus INV-1..INV-4 (INV-5 is
// D-13/D-14 territory, out of this microtask).

uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('writerTestOpportunity')) {
    /** A plain, non-synchronized opportunity (no product line -> QuoteManagerSyncMode::isSynchronized() is false). */
    function writerTestOpportunity(): Opportunity
    {
        return Opportunity::factory()->create();
    }
}

if (! function_exists('writerTestSynchronizedOpportunity')) {
    /** An opportunity whose one covered category satisfies BOTH D-7 settings. */
    function writerTestSynchronizedOpportunity(): Opportunity
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'single_quote_per_opportunity' => true,
            'management_mode' => CategoryManagementMode::Single,
        ]);

        $opportunity = Opportunity::factory()->create();
        $opportunity->productLines()->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

// ---------------------------------------------------------------------------
// Step 2: full-replace pivot sync
// ---------------------------------------------------------------------------

it('Step 2: syncs the quote_user pivot, gap-aware, index+1 = position', function () {
    $opportunity = writerTestOpportunity();
    $ga1 = User::factory()->create();
    $ga3 = User::factory()->create();
    $opportunity->managers()->sync([$ga1->id => ['position' => 1], $ga3->id => ['position' => 3]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    app(QuoteManagerWriter::class)->sync($quote, [$ga1->id, null, $ga3->id], promoteToOpportunity: false);

    $pivot = $quote->managers()->get();
    expect($pivot->pluck('id')->all())->toBe([$ga1->id, $ga3->id])
        ->and($pivot->firstWhere('id', $ga1->id)->pivot->position)->toBe(1)
        ->and($pivot->firstWhere('id', $ga3->id)->pivot->position)->toBe(3);
});

it('Step 2: a subsequent sync() is a full replace, not a merge', function () {
    $opportunity = writerTestOpportunity();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $opportunity->managers()->sync([$first->id => ['position' => 1], $second->id => ['position' => 2]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $writer = app(QuoteManagerWriter::class);

    $writer->sync($quote, [$first->id], promoteToOpportunity: false);
    $writer->sync($quote, [$second->id], promoteToOpportunity: false);

    expect($quote->managers()->get()->pluck('id')->all())->toBe([$second->id]);
});

// ---------------------------------------------------------------------------
// Step 3 / INV-2: quotes.operator_id mirrors the OPERATOR slot
// ---------------------------------------------------------------------------

it('Step 3/INV-2: operator_id is written from the OPERATOR (position 2) slot', function () {
    $opportunity = writerTestOpportunity();
    $ga1 = User::factory()->create();
    $operator = User::factory()->create();
    $opportunity->managers()->sync([$ga1->id => ['position' => 1], $operator->id => ['position' => ManagerPositions::OPERATOR]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    app(QuoteManagerWriter::class)->sync($quote, [$ga1->id, $operator->id], promoteToOpportunity: false);

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});

it('Step 3/INV-2: operator_id is nulled when the OPERATOR slot is cleared', function () {
    $opportunity = writerTestOpportunity();
    $operator = User::factory()->create();
    $opportunity->managers()->sync([$operator->id => ['position' => ManagerPositions::OPERATOR]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $writer = app(QuoteManagerWriter::class);

    $writer->sync($quote, [null, $operator->id], promoteToOpportunity: false);
    expect($quote->fresh()->operator_id)->toBe($operator->id);

    $writer->sync($quote, [], promoteToOpportunity: false);
    expect($quote->fresh()->operator_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// Step 1 / D-6 / INV-3: appartenenza outside synchronized categories
// ---------------------------------------------------------------------------

it('Step 1/INV-3: rejects a user who is not a Gestore Account of the opportunity, with a 422 naming them', function () {
    $opportunity = writerTestOpportunity();
    $stranger = User::factory()->create(['name' => 'Utente Estraneo']);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    try {
        app(QuoteManagerWriter::class)->sync($quote, [$stranger->id], promoteToOpportunity: false);
        expect(false)->toBeTrue('expected a ValidationException');
    } catch (ValidationException $exception) {
        expect($exception->errors()['manager_slots'][0])->toContain('Utente Estraneo');
    }

    expect($quote->fresh()->managers()->count())->toBe(0);
});

it('Step 1/AC-005: promote_managers_to_opportunity=true appends the missing user to the opportunity\'s first FREE slot, without moving anyone else', function () {
    $opportunity = writerTestOpportunity();
    $existing = User::factory()->create();
    $opportunity->managers()->sync([$existing->id => ['position' => 1]]);
    $newcomer = User::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    app(QuoteManagerWriter::class)->sync($quote, [null, $newcomer->id], promoteToOpportunity: true);

    $opportunityManagers = $opportunity->managers()->get();
    expect($opportunityManagers->pluck('id')->all())->toContain($existing->id, $newcomer->id)
        ->and($opportunityManagers->firstWhere('id', $existing->id)->pivot->position)->toBe(1)
        ->and($opportunityManagers->firstWhere('id', $newcomer->id)->pivot->position)->toBe(2);
});

it('Step 1: appartenenza is satisfied (no exception) once every mapped user is already a Gestore Account', function () {
    $opportunity = writerTestOpportunity();
    $member = User::factory()->create();
    $opportunity->managers()->sync([$member->id => ['position' => 1]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    app(QuoteManagerWriter::class)->sync($quote, [$member->id], promoteToOpportunity: false);

    expect($quote->managers()->get()->pluck('id')->all())->toBe([$member->id]);
});

// ---------------------------------------------------------------------------
// Step 4 / D-7 / INV-4: synchronized categories replicate onto the opportunity
// ---------------------------------------------------------------------------

it('Step 4/INV-4: in synchronized mode, writing the quote REPLACES the opportunity\'s own GA list wholesale', function () {
    $opportunity = writerTestSynchronizedOpportunity();
    $stale = User::factory()->create();
    $opportunity->managers()->sync([$stale->id => ['position' => 1]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $fresh = User::factory()->create();

    // D-6/D-7: appartenenza is inert here — $fresh is NOT already a
    // Gestore Account of the opportunity, yet no exception is thrown, and
    // promoteToOpportunity is left false to prove the rule never engages.
    app(QuoteManagerWriter::class)->sync($quote, [$fresh->id], promoteToOpportunity: false);

    $quoteManagers = $quote->managers()->get()->pluck('id')->all();
    $opportunityManagers = $opportunity->managers()->get()->pluck('id')->all();

    expect($quoteManagers)->toBe([$fresh->id])
        ->and($opportunityManagers)->toBe([$fresh->id])
        ->and($opportunityManagers)->not->toContain($stale->id);
});

it('Step 4: outside synchronized mode, the opportunity pivot is left untouched', function () {
    $opportunity = writerTestOpportunity();
    $ga = User::factory()->create();
    $opportunity->managers()->sync([$ga->id => ['position' => 1]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    app(QuoteManagerWriter::class)->sync($quote, [$ga->id], promoteToOpportunity: false);

    expect($opportunity->managers()->get()->pluck('id')->all())->toBe([$ga->id]);
});
