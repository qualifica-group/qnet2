<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * ENFORCEMENT of `single_quote_per_opportunity` (user directive 2026-08-07):
 * an opportunity covered by a flagged product category accepts ONE quote. The
 * flag's own inheritance contract is covered by
 * tests/Feature/ProductCategories/ProductCategorySingleQuoteTest.php.
 */
uses(RefreshDatabase::class);

if (! function_exists('singleQuoteActor')) {
    function singleQuoteActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['quotes.create', 'quotes.update']);

        QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();

        Sanctum::actingAs($user);

        return $user;
    }
}

if (! function_exists('singleQuoteOpportunity')) {
    /** An opportunity whose one covered category carries the flag as given. */
    function singleQuoteOpportunity(bool $singleQuote): Opportunity
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'single_quote_per_opportunity' => $singleQuote,
        ]);

        $opportunity = Opportunity::factory()->create();
        $opportunity->productLines()->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

it('the FIRST quote on a flagged opportunity is accepted', function () {
    singleQuoteActor();
    $opportunity = singleQuoteOpportunity(true);

    $this->postJson('/api/quotes', ['title' => 'Prima offerta', 'opportunity_id' => $opportunity->id])
        ->assertCreated();
});

it('a SECOND quote on a flagged opportunity is rejected, naming opportunity_id', function () {
    singleQuoteActor();
    $opportunity = singleQuoteOpportunity(true);
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $this->postJson('/api/quotes', ['title' => 'Seconda offerta', 'opportunity_id' => $opportunity->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('opportunity_id');

    expect(Quote::where('opportunity_id', $opportunity->id)->count())->toBe(1);
});

it('an unflagged opportunity still takes several quotes', function () {
    singleQuoteActor();
    $opportunity = singleQuoteOpportunity(false);
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $this->postJson('/api/quotes', ['title' => 'Seconda offerta', 'opportunity_id' => $opportunity->id])
        ->assertCreated();

    expect(Quote::where('opportunity_id', $opportunity->id)->count())->toBe(2);
});

it('an opportunity with no product line is never capped (rule indeterminate)', function () {
    singleQuoteActor();
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $this->postJson('/api/quotes', ['title' => 'Seconda offerta', 'opportunity_id' => $opportunity->id])
        ->assertCreated();
});

it('grandfathering: an already-multi-quote opportunity stays editable once the flag is turned on', function () {
    singleQuoteActor();
    $opportunity = singleQuoteOpportunity(true);
    $first = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $this->patchJson("/api/quotes/{$first->id}", ['title' => 'Titolo corretto'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Titolo corretto');
});
