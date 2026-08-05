<?php

use App\Jobs\GenerateExportJob;
use App\Models\Attachment;
use App\Models\BusinessFunction;
use App\Models\ExportRun;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityTableUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityTableUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewDocuments'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-040 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/opportunities/columns: 200 with the declared columns, 403 without viewAny (AC-040)', function () {
    $actor = opportunityTableUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/opportunities/columns')->assertForbidden();

    $actor = opportunityTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/opportunities/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('opportunities')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']]);

    $ids = collect($data['columns'])->pluck('id')->all();
    // requirement changed (spec 0082): `status` is a COMPUTED column (from the
    // row's quotes), right after `operational_site`. `managers` (opportunity_user
    // pivot, avatar stack) sits next to `supervisor`. Requirement changed (user
    // directive 2026-07-23): `products_of_interest` follows the two aggregated
    // classification columns.
    expect($ids)->toBe([
        'id', 'name', 'registry', 'referent', 'commercial', 'supervisor', 'managers', 'source', 'operational_site', 'status',
        'product_category', 'business_function', 'products_of_interest', 'estimated_value', 'success_probability', 'start_date',
        'expected_close_date', 'created_at',
    ]);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['id']['type'])->toBe('number')
        ->and($columns['id']['visible'])->toBeFalse()
        ->and($columns['id']['sortable'])->toBeTrue()
        ->and($columns['id']['filterable'])->toBeFalse()
        ->and($columns['id']['filterType'])->toBeNull()
        ->and($columns['registry']['sortable'])->toBeTrue()
        ->and($columns['registry']['filterType'])->toBe('set')
        ->and($columns['estimated_value']['filterType'])->toBe('number');

    // Amendment rev.3: the 2 AGGREGATED (to-many) columns are filterable but
    // NOT sortable — no single related row to order by (AC-105). The `managers`
    // to-many column shares that shape: filterable (set), never sortable.
    expect($columns['product_category']['filterType'])->toBe('set')
        ->and($columns['product_category']['sortable'])->toBeFalse()
        ->and($columns['business_function']['filterType'])->toBe('set')
        ->and($columns['business_function']['sortable'])->toBeFalse()
        ->and($columns['managers']['filterType'])->toBe('set')
        ->and($columns['managers']['sortable'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-041 — filter on the registry column applied to the ENTIRE dataset;
// sort on a derived column via allow-list subquery
// ---------------------------------------------------------------------------

it('rows: a filter on the registry column returns only that registry\'s opportunities (AC-041)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $registry = Registry::factory()->create(['name' => 'Match Registry']);
    $otherRegistry = Registry::factory()->create(['name' => 'Other Registry']);
    $matching = Opportunity::factory()->create(['registry_id' => $registry->id]);
    Opportunity::factory()->create(['registry_id' => $otherRegistry->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['registry' => ['filterType' => 'set', 'values' => ['Match Registry']]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$matching->id]);
});

it('rows: sorting by referent orders opportunities by the related referent name via allow-list subquery (AC-041)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $referentAlpha = Referent::factory()->create(['name' => 'Alpha Referent']);
    $referentZulu = Referent::factory()->create(['name' => 'Zulu Referent']);
    $opportunityZulu = Opportunity::factory()->create(['referent_id' => $referentZulu->id]);
    $opportunityAlpha = Opportunity::factory()->create(['referent_id' => $referentAlpha->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'referent', 'sort' => 'asc']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$opportunityAlpha->id, $opportunityZulu->id]);
});

// ---------------------------------------------------------------------------
// AC-009/010 (spec 0056) — operational_site sorts/filters (set + advanced)
// by the site's PRIMARY address line1, never the composed label
// ---------------------------------------------------------------------------

it('rows: sorting by operational_site orders opportunities by the site\'s primary address line1 (AC-009)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $siteA = OperationalSite::factory()->create();
    $siteA->addresses()->create(['line1' => 'Alpha Street', 'is_primary' => true]);
    $siteB = OperationalSite::factory()->create();
    $siteB->addresses()->create(['line1' => 'Zulu Street', 'is_primary' => true]);
    $opportunityZulu = Opportunity::factory()->create(['operational_site_id' => $siteB->id]);
    $opportunityAlpha = Opportunity::factory()->create(['operational_site_id' => $siteA->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'operational_site', 'sort' => 'asc']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$opportunityAlpha->id, $opportunityZulu->id]);
});

it('rows: a set filter on operational_site matches the site\'s primary address line1, bound (AC-010)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Roma 1', 'is_primary' => true]);
    $matching = Opportunity::factory()->create(['operational_site_id' => $site->id]);
    Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['operational_site' => ['filterType' => 'set', 'values' => ['Via Roma 1']]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('rows: the operational_site advanced (text) filter matches a substring of the primary address line1 (AC-010)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Corso Milano 10', 'is_primary' => true]);
    $matching = Opportunity::factory()->create(['operational_site_id' => $site->id]);
    Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['operational_site' => 'Milano'],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('rows: operational_site is the composed "{line1} - {city}" label (AC-006/015), no "[object Object]"', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $site = OperationalSite::factory()->withAddress()->create();
    $site->addresses()->first()->update(['line1' => 'Via Roma 1']);
    $opportunity = Opportunity::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $opportunity->id);

    expect($row['operational_site'])->toBeArray()
        ->and($row['operational_site']['id'])->toBe($site->id)
        ->and($row['operational_site']['label'])->toStartWith('Via Roma 1 - ');
});

it('rows: an unknown sort column is rejected (allow-list, no raw SQL escape hatch)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'lead_id', 'sort' => 'asc']],
    ])->assertStatus(422);
});

it('rows: an unknown sort column is rejected for the AGGREGATED product_category column too (not sortable, AC-105)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'product_category', 'sort' => 'asc']],
    ])->assertStatus(422);
});

it('rows: product_category/business_function are comma-joined display strings and filter via whereHas (AC-105)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $businessFunction = BusinessFunction::factory()->create(['name' => 'Vendite']);
    $categoryOne = ProductCategory::factory()->create(['name' => 'Cloud', 'business_function_id' => $businessFunction->id]);
    $categoryTwo = ProductCategory::factory()->create(['name' => 'On-Prem', 'business_function_id' => $businessFunction->id]);
    $matching = Opportunity::factory()->create();
    $matching->productLines()->createMany([
        ['business_function_id' => $businessFunction->id, 'product_category_id' => $categoryOne->id],
        ['business_function_id' => $businessFunction->id, 'product_category_id' => $categoryTwo->id],
    ]);
    Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $matching->id);

    expect($row['product_category'])->toBe('Cloud, On-Prem');
    expect($row['business_function'])->toBe('Vendite');

    $filtered = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['product_category' => ['filterType' => 'set', 'values' => ['Cloud']]],
    ])->assertOk();

    $ids = collect($filtered->json('items'))->pluck('id');
    expect($ids->all())->toBe([$matching->id]);
});

it('rows: registry/referent/commercial/supervisor/source surface as {id, name} summaries', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $registry = Registry::factory()->create(['name' => 'Acme Spa']);
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $opportunity->id);

    expect($row['registry'])->toMatchArray(['id' => $registry->id, 'name' => 'Acme Spa']);
    expect($row['referent'])->toBeNull();
});

it('rows: supervisor carries avatar_url and managers surface as an ordered avatar-stack array filterable by name', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $supervisor = User::factory()->create(['name' => 'Dana Ricci']);
    $managerOne = User::factory()->create(['name' => 'Bruno Sala']);
    $managerTwo = User::factory()->create(['name' => 'Aldo Neri']);
    $opportunity = Opportunity::factory()->create(['supervisor_id' => $supervisor->id]);
    $opportunity->managers()->attach([
        $managerOne->id => ['position' => 1],
        $managerTwo->id => ['position' => 2],
    ]);
    Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $opportunity->id);

    // supervisor: {id, name, avatar_url} (avatar_url null when none uploaded).
    expect($row['supervisor'])->toMatchArray(['id' => $supervisor->id, 'name' => 'Dana Ricci'])
        ->and($row['supervisor'])->toHaveKey('avatar_url');

    // managers: ordered by pivot position, each an {id, name, avatar_url} summary.
    expect(collect($row['managers'])->pluck('name')->all())->toBe(['Bruno Sala', 'Aldo Neri'])
        ->and($row['managers'][0])->toHaveKeys(['id', 'name', 'avatar_url']);

    // A set filter on managers scopes to opportunities having that manager.
    $filtered = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['managers' => ['filterType' => 'set', 'values' => ['Bruno Sala']]],
    ])->assertOk();

    expect(collect($filtered->json('items'))->pluck('id')->all())->toBe([$opportunity->id]);
});

// ---------------------------------------------------------------------------
// `documents` row action + documents_count (HasAttachments 'documents'
// collection, gated by OpportunityPolicy::viewDocuments)
// ---------------------------------------------------------------------------

it('row.actions contains documents for an actor with opportunities.viewDocuments', function () {
    $actor = opportunityTableUserWith(['viewAny', 'viewDocuments']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $opportunity->id)['actions'])->toContain('documents');
});

it('row.actions omits documents for an actor without opportunities.viewDocuments', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $opportunity->id)['actions'])->not->toContain('documents');
});

it('rows: documents_count reflects only the documents-collection attachments of that opportunity', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $withDocuments = Opportunity::factory()->create();
    $otherOpportunity = Opportunity::factory()->create();

    Attachment::factory()->for($withDocuments, 'attachable')->count(2)->create(['collection' => 'documents']);
    // A different collection on the SAME opportunity must not be counted.
    Attachment::factory()->for($withDocuments, 'attachable')->create(['collection' => 'other']);
    // An attachment in 'documents' on a DIFFERENT opportunity must not leak in.
    Attachment::factory()->for($otherOpportunity, 'attachable')->create(['collection' => 'documents']);

    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $withDocuments->id)['documents_count'])->toBe(2)
        ->and($items->firstWhere('id', $otherOpportunity->id)['documents_count'])->toBe(1);
});

it('rows: documents_count is 0 when the opportunity has no attachments', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $opportunity->id)['documents_count'])->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-042 — export: created with opportunities.export, 403 without it
// ---------------------------------------------------------------------------

if (! function_exists('opportunityExportPayload')) {
    /**
     * @return array<string, mixed>
     */
    function opportunityExportPayload(): array
    {
        return [
            'format' => 'csv',
            'columns' => [
                ['colId' => 'name', 'header' => 'Name'],
                ['colId' => 'registry', 'header' => 'Registry'],
            ],
        ];
    }
}

it('201 creates the ExportRun and dispatches GenerateExportJob with opportunities.export (AC-042)', function () {
    Queue::fake();
    $actor = opportunityTableUserWith(['export']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/opportunities', opportunityExportPayload())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.export_run.status', 'processing')
        ->assertJsonPath('data.export_run.resource', 'opportunities')
        ->assertJsonPath('data.export_run.format', 'csv');

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));
    expect($run->user_id)->toBe($actor->id);

    Queue::assertPushed(GenerateExportJob::class);
});

it('403 without opportunities.export, no ExportRun created (AC-042)', function () {
    Queue::fake();
    $actor = opportunityTableUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/opportunities', opportunityExportPayload())->assertForbidden();

    expect(ExportRun::count())->toBe(0);
    Queue::assertNotPushed(GenerateExportJob::class);
});
