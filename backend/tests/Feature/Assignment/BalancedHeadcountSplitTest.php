<?php

use App\Enums\ImportStatus;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\EmploymentProfile;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0168 D-4 — `use_current_load` on the three "Smistamento equo"
 * endpoints. Every scenario has a BUSY operator (lower id, already holding
 * work) and a FREE one, and two records to distribute: weighing the load
 * sends both to the free operator, ignoring it splits them one each.
 */
if (! function_exists('headcountOperator')) {
    function headcountOperator(OperationalSite $site, BusinessFunction $function, ProductCategory $category): User
    {
        $operator = User::factory()->create();

        EmploymentProfile::factory()->for($operator)->physicalSite($site)->competentIn($function, $category)->create();

        return $operator;
    }
}

if (! function_exists('headcountCampaignOperators')) {
    /**
     * Busy (3 leads already) and free operators of $campaign's Sede,
     * competent for its own product line.
     *
     * @return array{0: User, 1: User}
     */
    function headcountCampaignOperators(Campaign $campaign): array
    {
        $line = $campaign->productLines()->firstOrFail();
        $site = OperationalSite::query()->findOrFail($campaign->operational_site_id);
        $function = BusinessFunction::query()->findOrFail($line->business_function_id);
        $category = ProductCategory::query()->findOrFail($line->product_category_id);

        $busy = headcountOperator($site, $function, $category);
        $free = headcountOperator($site, $function, $category);
        Lead::factory()->count(3)->create(['operator_id' => $busy->id]);

        return [$busy, $free];
    }
}

if (! function_exists('headcountLeadsActor')) {
    function headcountLeadsActor(): User
    {
        foreach (['viewAny', 'view', 'update'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $actor = User::factory()->create();
        $actor->givePermissionTo('leads.update');

        return $actor;
    }
}

if (! function_exists('headcountOffer')) {
    function headcountOffer(OperationalSite $site, BusinessFunction $function, ProductCategory $category, ?User $operator = null): Quote
    {
        $opportunity = Opportunity::factory()->create();

        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $function->id,
            'product_category_id' => $category->id,
        ]);

        $statusId = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', 'open')->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system('open')->create()->id;

        return Quote::factory()->for($opportunity)->create([
            'operational_site_id' => $site->id,
            'operator_id' => $operator?->id,
            'quote_workflow_status_id' => $statusId,
        ]);
    }
}

// ---------------------------------------------------------------------------
// Leads
// ---------------------------------------------------------------------------

it('0168 D-4: leads balanced weighs the current load by default', function () {
    $actor = headcountLeadsActor();
    $campaign = Campaign::factory()->create(['operational_site_id' => OperationalSite::factory()->withAddress()->create()->id]);
    [, $free] = headcountCampaignOperators($campaign);
    $leads = Lead::factory()->count(2)->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => $leads->modelKeys(),
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect($leads->map(fn (Lead $lead): ?int => $lead->fresh()->operator_id)->all())->toBe([$free->id, $free->id]);
});

it('0168 D-4: leads balanced with use_current_load=false splits by headcount', function () {
    $actor = headcountLeadsActor();
    $campaign = Campaign::factory()->create(['operational_site_id' => OperationalSite::factory()->withAddress()->create()->id]);
    [$busy, $free] = headcountCampaignOperators($campaign);
    $leads = Lead::factory()->count(2)->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => $leads->modelKeys(),
        'mode' => 'balanced',
        'use_current_load' => false,
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect($leads->map(fn (Lead $lead): ?int => $lead->fresh()->operator_id)->all())->toBe([$busy->id, $free->id]);
});

it('0168 D-4: use_current_load is rejected outside balanced and must be boolean', function () {
    $actor = headcountLeadsActor();
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'single',
        'operator_id' => $actor->id,
        'use_current_load' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors('use_current_load');

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'balanced',
        'use_current_load' => 'sometimes',
    ])->assertUnprocessable()->assertJsonValidationErrors('use_current_load');
});

// ---------------------------------------------------------------------------
// Request management (offers): the load is the offers already operated.
// ---------------------------------------------------------------------------

it('0168 D-4: request-management balanced with use_current_load=false splits by headcount', function (bool $useCurrentLoad, string $expected) {
    foreach (['viewAny', 'view', 'update', 'viewAll', 'assignOperator'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.viewAny', 'request-management.viewAll', 'request-management.update', 'request-management.assignOperator']);

    $site = OperationalSite::factory()->withAddress()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $busy = headcountOperator($site, $function, $category);
    $free = headcountOperator($site, $function, $category);
    foreach (range(1, 3) as $ignored) {
        headcountOffer($site, $function, $category, $busy);
    }
    $first = headcountOffer($site, $function, $category);
    $second = headcountOffer($site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$first->id, $second->id],
        'mode' => 'balanced',
        'use_current_load' => $useCurrentLoad,
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    $expectedIds = $expected === 'split' ? [$busy->id, $free->id] : [$free->id, $free->id];
    expect([$first->fresh()->operator_id, $second->fresh()->operator_id])->toBe($expectedIds);
})->with([
    'weighing the load' => [true, 'free'],
    'by headcount' => [false, 'split'],
]);

// ---------------------------------------------------------------------------
// Import review rows: the load is the real leads already held.
// ---------------------------------------------------------------------------

it('0168 D-4: import rows balanced with use_current_load=false splits by headcount', function (bool $useCurrentLoad, string $expected) {
    Permission::findOrCreate('leads.import');
    $actor = User::factory()->create();
    $actor->givePermissionTo('leads.import');
    grantImportRunsPermissions($actor, ['update']);

    $campaign = Campaign::factory()->create(['operational_site_id' => OperationalSite::factory()->withAddress()->create()->id]);
    [$busy, $free] = headcountCampaignOperators($campaign);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'global_config' => [],
    ]);
    $rows = collect([1, 2])->map(fn (int $rowNumber): ImportRunRow => ImportRunRow::factory()->for($run, 'importRun')->create([
        'row_number' => $rowNumber,
        'mapped_values' => ['email' => "row{$rowNumber}@example.com", 'campaign_id' => $campaign->id],
    ]));
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => $rows->pluck('id')->all(),
        'use_current_load' => $useCurrentLoad,
    ])->assertOk()->assertJsonPath('data.updated', 2);

    $expectedIds = $expected === 'split' ? [$busy->id, $free->id] : [$free->id, $free->id];
    expect($rows->map(fn (ImportRunRow $row): ?int => $row->fresh()->operator_id)->all())->toBe($expectedIds);
})->with([
    'weighing the load' => [true, 'free'],
    'by headcount' => [false, 'split'],
]);
