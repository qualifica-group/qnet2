<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0130, D-9: POST /api/assignment/selection-scope for `domain=enrollees`
// — the SAME reading as `domain=quotes`, parameterized by
// `AssignmentDomain::requestModule()`. `enrollee-management.viewAny` gates
// it (never `request-management.viewAny`, never an OR of the two), and the
// D-2 status filter applies on top of the D-3 perimeter — an offer outside
// either contributes nothing to the answer.

uses(RefreshDatabase::class);

if (! function_exists('selScopeStatusId')) {
    function selScopeStatusId(string $systemKey): int
    {
        return QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
            ->where('system_key', $systemKey)
            ->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($systemKey)->create()->id;
    }
}

if (! function_exists('selScopeActor')) {
    /**
     * @param  array<int, string>  $abilities  fully-qualified ability names
     */
    function selScopeActor(array $abilities): User
    {
        foreach (['enrollee-management.viewAny', 'enrollee-management.viewAll', 'request-management.viewAny', 'request-management.viewAll'] as $ability) {
            Permission::findOrCreate($ability);
        }

        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            $actor->givePermissionTo($ability);
        }

        return $actor;
    }
}

if (! function_exists('selScopeQuoteWithCategory')) {
    function selScopeQuoteWithCategory(string $systemKey, BusinessFunction $function, ProductCategory $category): Quote
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $function->id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->create([
            'opportunity_id' => $opportunity->id,
            'quote_workflow_status_id' => selScopeStatusId($systemKey),
        ]);
    }
}

// ---------------------------------------------------------------------------
// Permission separation — never an OR of the two modules
// ---------------------------------------------------------------------------

it('domain=enrollees is 403 with only request-management.* (AC-003)', function () {
    $actor = selScopeActor(['request-management.viewAny', 'request-management.viewAll']);
    $quote = Quote::factory()->create(['quote_workflow_status_id' => selScopeStatusId('closed_won')]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'enrollees',
        'ids' => [$quote->id],
    ])->assertForbidden();
});

it('domain=quotes stays 403 with only enrollee-management.* — no leak the other way', function () {
    $actor = selScopeActor(['enrollee-management.viewAny', 'enrollee-management.viewAll']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$quote->id],
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// D-2 status filter + D-3 perimeter, evaluated on enrollee-management.* only
// ---------------------------------------------------------------------------

it('domain=enrollees with enrollee-management.viewAny+viewAll answers only the in-scope, validated/closed_won selection (AC-004)', function () {
    $actor = selScopeActor(['enrollee-management.viewAny', 'enrollee-management.viewAll']);
    $function = BusinessFunction::factory()->create();
    $inScopeCategory = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $outOfStateCategory = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $inScope = selScopeQuoteWithCategory('closed_won', $function, $inScopeCategory);
    $outOfState = selScopeQuoteWithCategory('open', $function, $outOfStateCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'enrollees',
        'ids' => [$inScope->id, $outOfState->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', [$inScopeCategory->id]);
});

it('domain=enrollees drops an offer outside the actor D-3 perimeter (viewAny alone)', function () {
    $actor = selScopeActor(['enrollee-management.viewAny']);
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $mine = selScopeQuoteWithCategory('validated', $function, $category);
    // `operator_id` is deliberately absent from Quote::$fillable (spec 0087,
    // D-9) — written only through RequestOperatorWriter, never
    // mass-assignment, so a plain `update()` would silently drop it.
    $mine->operator_id = $actor->id;
    $mine->save();
    $someoneElsesCategory = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $someoneElses = selScopeQuoteWithCategory('validated', $function, $someoneElsesCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'enrollees',
        'ids' => [$mine->id, $someoneElses->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', [$category->id]);
});

it('domain=quotes is unaffected: still no status filter, still request-management.* (parity)', function () {
    $actor = selScopeActor(['request-management.viewAny', 'request-management.viewAll']);
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $quote = selScopeQuoteWithCategory('open', $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$quote->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', [$category->id]);
});
