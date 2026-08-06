<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `OpportunityScopedTableDefinition` (spec 0067, D-1): scopes the `quotes`
 * domain's rows/values/columns endpoints to a single Opportunity via the
 * `opportunityId`/`opportunity_id` request parameter. AC-001..AC-014 (the
 * export scope, AC-015..018, is a separate lane's file).
 *
 * `quoteTableUserWith()` is declared (guarded) in QuoteTableTest.php, in the
 * same directory, and reused here as-is (mirrors how sibling
 * RequestManagement test files share `requestManagementUserWith()`).
 */
uses(RefreshDatabase::class);

it('AC-001: rows scoped to opportunity A returns exactly A\'s quotes, and pagination.total matches', function () {
    $actor = quoteTableUserWith(['viewAny']);
    $opportunityA = Opportunity::factory()->create();
    $opportunityB = Opportunity::factory()->create();
    Quote::factory()->count(3)->create(['opportunity_id' => $opportunityA->id]);
    Quote::factory()->count(2)->create(['opportunity_id' => $opportunityB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => $opportunityA->id,
    ])->assertOk();

    $items = $response->json('items');
    expect($items)->toHaveCount(3)
        ->and(collect($items)->pluck('opportunity.id')->unique()->all())->toBe([$opportunityA->id])
        ->and($response->json('pagination.total'))->toBe(3);
});

it('AC-002: omitting opportunityId returns every quote, unchanged from today', function () {
    $actor = quoteTableUserWith(['viewAny']);
    $opportunityA = Opportunity::factory()->create();
    $opportunityB = Opportunity::factory()->create();
    Quote::factory()->count(3)->create(['opportunity_id' => $opportunityA->id]);
    Quote::factory()->count(2)->create(['opportunity_id' => $opportunityB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    expect($response->json('pagination.total'))->toBe(5);
});

it('AC-003: the scope composes in AND with filterModel, never widened by it', function () {
    $actor = quoteTableUserWith(['viewAny']);
    $opportunityA = Opportunity::factory()->create();
    $opportunityB = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunityA->id, 'code' => 'QUO-1000']);
    $onlyOnB = Quote::factory()->create(['opportunity_id' => $opportunityB->id, 'code' => 'QUO-9999']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => $opportunityA->id,
        'filterModel' => ['code' => ['filterType' => 'text', 'type' => 'contains', 'filter' => $onlyOnB->code]],
    ])->assertOk();

    expect($response->json('items'))->toBeEmpty()
        ->and($response->json('pagination.total'))->toBe(0);
});

it('AC-004: the scope composes in AND with advancedFilters.opportunity, never scavalcato', function () {
    $actor = quoteTableUserWith(['viewAny']);
    $opportunityA = Opportunity::factory()->create();
    $opportunityB = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunityA->id]);
    Quote::factory()->create(['opportunity_id' => $opportunityB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => $opportunityA->id,
        'advancedFilters' => ['opportunity' => [$opportunityB->id]],
    ])->assertOk();

    expect($response->json('items'))->toBeEmpty();
});

it('AC-005: the scope composes in AND with the quick-search, excluding a match on another opportunity', function () {
    $actor = quoteTableUserWith(['viewAny']);
    $opportunityA = Opportunity::factory()->create();
    $opportunityB = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunityA->id]);
    $onlyOnB = Quote::factory()->create(['opportunity_id' => $opportunityB->id, 'title' => 'Rinnovo esclusivo Beta']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => $opportunityA->id,
        'search' => 'esclusivo Beta',
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->not->toContain($onlyOnB->id);
});

it('AC-006: opportunityId non-numeric or nonexistent -> 422; null -> accepted as absent', function () {
    $actor = quoteTableUserWith(['viewAny']);
    Quote::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => 'not-a-number',
    ])->assertStatus(422)->assertJsonValidationErrors('opportunityId');

    $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('opportunityId');

    $response = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => null,
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(2);
});

it('AC-007: a user without quotes.viewAny is denied even with opportunityId set', function () {
    $actor = quoteTableUserWith([]);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => $opportunity->id,
    ])->assertForbidden();
});

it('AC-008: values for quote_workflow_status scoped to opportunity A excludes statuses only present on B', function () {
    $actor = quoteTableUserWith(['viewAny']);
    $opportunityA = Opportunity::factory()->create();
    $opportunityB = Opportunity::factory()->create();
    $statusOnA = QuoteWorkflowStatus::factory()->create(['name' => 'In revisione']);
    $statusOnlyOnB = QuoteWorkflowStatus::factory()->create(['name' => 'Solo su B']);
    Quote::factory()->create(['opportunity_id' => $opportunityA->id, 'quote_workflow_status_id' => $statusOnA->id]);
    Quote::factory()->create(['opportunity_id' => $opportunityB->id, 'quote_workflow_status_id' => $statusOnlyOnB->id]);
    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/quotes/values', [
        'columnId' => 'quote_workflow_status', 'opportunityId' => $opportunityA->id,
    ])->assertOk()->json('data.values');

    expect($values)->toContain('In revisione')
        ->and($values)->not->toContain('Solo su B');
});

it('AC-009: GET columns response is byte-identical with and without opportunity_id', function () {
    $actor = quoteTableUserWith(['viewAny']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $unscoped = $this->getJson('/api/tables/quotes/columns')->assertOk()->json('data');
    $scoped = $this->getJson('/api/tables/quotes/columns?opportunity_id='.$opportunity->id)->assertOk()->json('data');

    expect($scoped)->toBe($unscoped);
});

it('AC-010: per-row actions in a scoped grid still come from QuotePolicy, never widened', function () {
    $actor = quoteTableUserWith(['viewAny', 'view', 'update']);
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => $opportunity->id,
    ])->assertOk();

    $row = $response->json('items.0');
    // spec 0070 adds `generate_document`, gated by the same `quotes.view` as
    // `view` itself — this actor legitimately gets both.
    expect($row['actions'])->toBe(['view', 'edit', 'generate_document'])
        ->and($row['actions'])->not->toContain('delete');
});

it('AC-011: opportunityId is a no-op on the non-scoped users/opportunities domains', function () {
    $userActor = User::factory()->create();
    foreach (['viewAny', 'view'] as $ability) {
        Permission::findOrCreate("users.{$ability}");
        Permission::findOrCreate("opportunities.{$ability}");
    }
    $userActor->givePermissionTo(['users.viewAny', 'opportunities.viewAny']);
    User::factory()->count(2)->create();
    Opportunity::factory()->count(2)->create();
    Sanctum::actingAs($userActor);

    // A REAL, existing opportunity id: `opportunityId` is validated by
    // Rule::exists for every domain (it is a generic FormRequest key, same
    // as productCategoryId), so a bogus id would 422 regardless of scoping —
    // the point here is that a VALID id changes nothing for these domains.
    $existingOpportunityId = Opportunity::factory()->create()->id;

    $usersUnscoped = $this->postJson('/api/tables/users/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('pagination.total');
    $usersWithOpportunityId = $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => $existingOpportunityId,
    ])->assertOk()->json('pagination.total');
    expect($usersWithOpportunityId)->toBe($usersUnscoped);

    $opportunitiesUnscoped = $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('pagination.total');
    $opportunitiesWithOpportunityId = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => $existingOpportunityId,
    ])->assertOk()->json('pagination.total');
    expect($opportunitiesWithOpportunityId)->toBe($opportunitiesUnscoped);
});

it('AC-012: request-management/productCategoryId scoping is unaffected by the new TableRegistry composition', function () {
    foreach (['viewAny', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.viewAny', 'request-management.viewAll']);
    Sanctum::actingAs($actor);

    // No product line at all -> an empty result is still a 200, proving the
    // AttributeScopedTableDefinition composition (and its own allow-lists)
    // still resolves correctly through the extra OpportunityScopedTableDefinition
    // wrap for an UNRELATED domain.
    $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => 999999,
    ])->assertStatus(422);
});

it('AC-013: saving preferences and filters for quotes works unchanged, no opportunity scope required', function () {
    $actor = quoteTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/quotes/preferences', [
        'columns' => [['id' => 'title', 'width' => 300]],
    ])->assertOk()->assertJsonPath('data.customized', true);

    $this->postJson('/api/tables/quotes/filters', [
        'filterModel' => ['code' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'QUO']],
    ])->assertOk()->assertJsonPath('data.filtersCustomized', true);

    // The saved state applies identically whether read from the plain list
    // page (no opportunity_id) or from the embedded panel (opportunity_id
    // set) — D-4: preferences/filters are NOT segmented by scope.
    $scoped = $this->getJson('/api/tables/quotes/columns?opportunity_id='.Opportunity::factory()->create()->id)
        ->assertOk()->json('data');

    expect($scoped['customized'])->toBeTrue()
        ->and($scoped['filtersCustomized'])->toBeTrue();
});
