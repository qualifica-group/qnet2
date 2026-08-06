<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Registry;
use App\Models\User;
use App\Models\UserTableFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0082 — the Opportunity's status is COMPUTED from its quotes, the
 * "Stati opportunita'" module is gone. Covers AC-005/006/008/009/010/011/012;
 * the resolver's own rules (AC-001..AC-004/AC-007) live in
 * tests/Unit/Services/Opportunities/OpportunityStatusResolverTest.php.
 *
 * Spec 0083 (D-2/D-8) re-targets the fallback source: the Opportunity no
 * longer carries its own working-state FK at all — a quote-less opportunity
 * displays the GLOBAL default quote-workflow set's `open` row, a real,
 * user-configurable QuoteWorkflowStatus row (`quote_workflow_id IS NULL`),
 * never a per-opportunity override.
 */
uses(RefreshDatabase::class);

if (! function_exists('computedStatusActor')) {
    function computedStatusActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.viewAny', 'opportunities.view', 'opportunities.create', 'opportunities.update']);

        return $user;
    }
}

if (! function_exists('computedStatusCreatePayload')) {
    /**
     * @return array<string, mixed>
     */
    function computedStatusCreatePayload(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-005/AC-006 — the field is neither writable nor readable any more
// ---------------------------------------------------------------------------

it('create: submitting opportunity_status_id -> 422 prohibited (AC-005)', function () {
    Sanctum::actingAs(computedStatusActor());

    $this->postJson('/api/opportunities', [...computedStatusCreatePayload(), 'opportunity_status_id' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['opportunity_status_id']);

    expect(Opportunity::query()->count())->toBe(0);
});

it('create: without opportunity_status_id -> 201 (AC-005)', function () {
    Sanctum::actingAs(computedStatusActor());

    $this->postJson('/api/opportunities', computedStatusCreatePayload())->assertCreated();

    expect(Opportunity::query()->count())->toBe(1);
});

it('update: submitting opportunity_status_id -> 422 prohibited (AC-005)', function () {
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(computedStatusActor());

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['opportunity_status_id' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['opportunity_status_id']);
});

it('detail: exposes `status` and no longer exposes opportunity_status (AC-006)', function () {
    $opportunity = Opportunity::factory()->create();
    $status = QuoteWorkflowStatus::factory()->create(['name' => 'Da approvare', 'color' => 'blue']);
    Quote::factory()->count(2)->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $status->id]);
    Sanctum::actingAs(computedStatusActor());

    $data = $this->getJson("/api/opportunities/{$opportunity->id}")->assertOk()->json('data');

    expect($data['status'])->toBe([
        'source' => 'quotes',
        'distinct_count' => 1,
        'entries' => [['id' => $status->id, 'name' => 'Da approvare', 'color' => 'blue', 'group' => $status->group->value, 'count' => 2]],
    ])
        ->and($data)->not->toHaveKey('opportunity_status')
        ->and($data)->not->toHaveKey('opportunity_status_id');
});

// ---------------------------------------------------------------------------
// AC-008/AC-009 — the grid column: computed cell, set filter, never sortable
// ---------------------------------------------------------------------------

it('rows: the `status` cell carries the computed summary', function () {
    $opportunity = Opportunity::factory()->create();
    $first = QuoteWorkflowStatus::factory()->create(['name' => 'In lavorazione', 'sort_order' => 20]);
    $second = QuoteWorkflowStatus::factory()->create(['name' => 'In corso', 'sort_order' => 30]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $first->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $second->id]);
    Sanctum::actingAs(computedStatusActor());

    $row = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->firstWhere('id', $opportunity->id);

    expect($row['status']['distinct_count'])->toBe(2)
        ->and(array_column($row['status']['entries'], 'name'))->toBe(['In lavorazione', 'In corso']);
});

it('columns: `status` is filterable via `set` but never sortable or editable (AC-009)', function () {
    Sanctum::actingAs(computedStatusActor());

    $columns = collect($this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns)->toHaveKey('status')
        ->and($columns)->not->toHaveKey('opportunity_status');

    expect($columns['status']['sortable'])->toBeFalse()
        ->and($columns['status']['filterable'])->toBeTrue()
        ->and($columns['status']['filterType'])->toBe('set')
        ->and($columns['status']['editable'] ?? false)->toBeFalse();
});

it('rows: the `status` set filter matches the DISPLAYED status, quotes branch (AC-008)', function () {
    $wanted = QuoteWorkflowStatus::factory()->create(['name' => 'Da approvare']);
    $other = QuoteWorkflowStatus::factory()->create(['name' => 'Da firmare']);

    $matching = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $matching->id, 'quote_workflow_status_id' => $wanted->id]);

    $nonMatching = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $nonMatching->id, 'quote_workflow_status_id' => $other->id]);

    $quoteless = Opportunity::factory()->create();

    Sanctum::actingAs(computedStatusActor());

    $ids = collect($this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['Da approvare']]],
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$matching->id])
        ->and($ids)->not->toContain($nonMatching->id)
        ->and($ids)->not->toContain($quoteless->id);
});

it('rows: the `status` set filter matches the GLOBAL default open row ONLY for quote-less rows (AC-008, spec 0083 D-8)', function () {
    $globalOpen = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    $globalOpen->update(['name' => 'Da lavorare']);

    // Every quote-less opportunity displays the SAME global default `open`
    // row (D-8) — there is no per-opportunity working-state override any
    // more (the Opportunity's own working-state column no longer exists).
    $quoteless = Opportunity::factory()->create();

    // Has a quote -> the quote's own status is what it displays, so the
    // global default's name must not match it even though it shares no
    // status with it at all.
    $withQuote = Opportunity::factory()->create();
    Quote::factory()->create([
        'opportunity_id' => $withQuote->id,
        'quote_workflow_status_id' => QuoteWorkflowStatus::factory()->create(['name' => 'Da approvare'])->id,
    ]);

    Sanctum::actingAs(computedStatusActor());

    $ids = collect($this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['Da lavorare']]],
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$quoteless->id]);
});

it('values: the `status` option list unions quote statuses and the global default open row (spec 0083 D-8)', function () {
    $quoteStatus = QuoteWorkflowStatus::factory()->create(['name' => 'Da approvare']);
    $globalOpen = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    $globalOpen->update(['name' => 'Da lavorare']);

    $withQuote = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $withQuote->id, 'quote_workflow_status_id' => $quoteStatus->id]);

    // A quote-less opportunity is what pulls the global default's name into
    // the union (D-8) — without one, only quote statuses in use would appear.
    Opportunity::factory()->create();

    Sanctum::actingAs(computedStatusActor());

    $values = $this->postJson('/api/tables/opportunities/values', ['columnId' => 'status'])
        ->assertOk()->json('data.values');

    expect($values)->toBe(['Da approvare', 'Da lavorare']);
});

// ---------------------------------------------------------------------------
// AC-010/AC-011/AC-012 — the module is gone, schema and catalogue included
// ---------------------------------------------------------------------------

it('the opportunity-statuses routes are gone (AC-010)', function () {
    Sanctum::actingAs(computedStatusActor());

    $this->getJson('/api/opportunity-statuses/for-select')->assertNotFound();
    $this->getJson('/api/opportunity-statuses/1')->assertNotFound();
    $this->postJson('/api/opportunity-statuses', ['name' => 'X'])->assertNotFound();
    $this->postJson('/api/tables/opportunity-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertNotFound();
});

it('the table and the column are gone from the schema (AC-010)', function () {
    expect(Schema::hasTable('opportunity_statuses'))->toBeFalse()
        ->and(Schema::hasColumn('opportunities', 'opportunity_status_id'))->toBeFalse();
});

it('no opportunity-statuses permission survives the prune (AC-011)', function () {
    expect(Permission::query()->where('name', 'like', 'opportunity-statuses.%')->exists())->toBeFalse();
});

it('the navigation no longer offers the opportunity-statuses entry (AC-012)', function () {
    Sanctum::actingAs(computedStatusActor());

    $navigation = $this->getJson('/api/navigation')->assertOk()->json();

    expect(json_encode($navigation))->not->toContain('opportunity-statuses');
});

it('the opportunities field catalogue no longer carries opportunity_status_id (AC-012)', function () {
    Sanctum::actingAs(computedStatusActor());

    $fields = collect($this->getJson('/api/meta/opportunities')->assertOk()->json('data.fields'))
        ->pluck('key')
        ->all();

    expect($fields)->not->toContain('opportunity_status_id');
});

// ---------------------------------------------------------------------------
// AC-016 — persisted state referencing the removed column stays harmless.
// The generic engine REJECTS a filter on a non-filterable column (422, by
// design — the allow-list is a security boundary), so the guarantee tested
// here is the one that actually protects the grid: nothing persisted can
// carry the removed column back to the client.
// ---------------------------------------------------------------------------

it('a pre-existing saved filter state on the removed column is dropped on read (AC-016)', function () {
    Opportunity::factory()->create();
    $actor = computedStatusActor();

    // A row saved BEFORE this spec removed the column — written straight to
    // the store, since the endpoint itself now rejects the column.
    UserTableFilter::query()->create([
        'user_id' => $actor->id,
        'domain' => 'opportunities',
        'filters' => ['opportunity_status' => ['filterType' => 'set', 'values' => ['Nuova']]],
    ]);

    Sanctum::actingAs($actor);

    $config = $this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data');

    expect(json_encode($config))->not->toContain('opportunity_status');

    // And the grid still loads.
    $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
});

it('saving a filter on the removed column is rejected outright (AC-016)', function () {
    Sanctum::actingAs(computedStatusActor());

    $this->postJson('/api/tables/opportunities/filters', [
        'filterModel' => ['opportunity_status' => ['filterType' => 'set', 'values' => ['Nuova']]],
    ])->assertStatus(422);
});
