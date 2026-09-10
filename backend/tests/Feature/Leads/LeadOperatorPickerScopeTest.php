<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\CampaignProductLine;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Tables\Leads\LeadColumnCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Direttiva utente 2026-09-10: the Operatore column's INLINE picker used to
// list every user. It must offer the same operators the import assignment
// offers — those of the Sede of the lead's campaign (spec 0113, D-3)
// competent for the lead's required categories (spec 0110) — so the grid
// carries two non-visible row keys the editor sends to `users/for-select`.

uses(RefreshDatabase::class);

if (! function_exists('operatorScopeActor')) {
    /** @param  array<int, string>  $abilities */
    function operatorScopeActor(array $abilities = ['leads.viewAny']): User
    {
        foreach (['leads.viewAny', 'leads.update'] as $ability) {
            Permission::findOrCreate($ability);
        }

        $actor = User::factory()->create();
        $actor->givePermissionTo($abilities);

        return $actor;
    }
}

if (! function_exists('operatorScopeCategory')) {
    function operatorScopeCategory(): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
    }
}

if (! function_exists('operatorScopeCampaign')) {
    /**
     * A standalone campaign with (or without) a Sede, classified with exactly
     * $category — CampaignFactory mints a product line of its own (spec 0094
     * D-1), dropped first so these tests assert on an EXACT requirement set.
     */
    function operatorScopeCampaign(?OperationalSite $site, ?ProductCategory $category): Campaign
    {
        $campaign = Campaign::factory()->create(['operational_site_id' => $site?->id]);
        $campaign->productLines()->delete();

        if ($category !== null) {
            CampaignProductLine::factory()->create([
                'campaign_id' => $campaign->id,
                'product_category_id' => $category->id,
            ]);
        }

        return $campaign;
    }
}

if (! function_exists('operatorScopeRow')) {
    /** @return array<string, mixed> */
    function operatorScopeRow(): array
    {
        return test()->postJson('/api/tables/leads/rows', ['startRow' => 0, 'endRow' => 25])
            ->assertOk()
            ->json('items.0');
    }
}

// ---------------------------------------------------------------------------
// The config the grid builds the editor from
// ---------------------------------------------------------------------------

it('declares the Operatore picker scope as {Sede of the campaign, required categories}', function () {
    Sanctum::actingAs(operatorScopeActor(['leads.viewAny', 'leads.update']));

    $column = collect($this->getJson('/api/tables/leads/columns')->assertOk()->json('data.columns'))
        ->keyBy('id')['operator'];

    expect($column['relation']['scope'])->toBe([
        'operational_site_id' => 'assignment_site_id',
        'competence_category_ids' => 'assignment_category_ids',
    ])
        // Narrowing only (spec 0110 R-1): a non-competent operator picked by
        // hand is still accepted, so the escape must stay available.
        ->and($column['relation'])->not->toHaveKey('lockScope')
        // Everything else about the column is untouched.
        ->and($column['label'])->toBe('leads.columns.operator')
        ->and($column['type'])->toBe('text')
        ->and($column['editable'])->toBeTrue()
        ->and($column['editor'])->toBe('relation')
        ->and($column['relation']['resource'])->toBe('users')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeTrue();

    // `editableField`/`nullable` are not part of the emitted config (the
    // write path reads them off the raw declaration): asserted at the source.
    $declared = collect(LeadColumnCatalog::columns())->keyBy('id')['operator'];

    expect($declared['editableField'])->toBe('operator_id')
        ->and($declared['nullable'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// The two row keys
// ---------------------------------------------------------------------------

it('answers assignment_site_id from the CAMPAIGN Sede, never from leads.operational_site_id', function () {
    $campaignSite = OperationalSite::factory()->create();
    $ownSite = OperationalSite::factory()->create();
    $campaign = operatorScopeCampaign($campaignSite, operatorScopeCategory());
    Lead::factory()->create(['campaign_id' => $campaign->id, 'operational_site_id' => $ownSite->id]);
    Sanctum::actingAs(operatorScopeActor());

    expect(operatorScopeRow()['assignment_site_id'])->toBe($campaignSite->id);
});

it('answers a null assignment_site_id when the campaign carries no Sede', function () {
    $campaign = operatorScopeCampaign(null, operatorScopeCategory());
    Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs(operatorScopeActor());

    expect(operatorScopeRow()['assignment_site_id'])->toBeNull();
});

it('answers assignment_category_ids from the lead OWN products of interest', function () {
    $ownCategory = operatorScopeCategory();
    $campaign = operatorScopeCampaign(OperationalSite::factory()->create(), operatorScopeCategory());
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    $lead->productsOfInterest()->sync([Product::factory()->create(['category_id' => $ownCategory->id])->id]);
    Sanctum::actingAs(operatorScopeActor());

    expect(operatorScopeRow()['assignment_category_ids'])->toBe([$ownCategory->id]);
});

it('falls back to the campaign categories for a lead with no product of interest', function () {
    $campaignCategory = operatorScopeCategory();
    $campaign = operatorScopeCampaign(OperationalSite::factory()->create(), $campaignCategory);
    Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs(operatorScopeActor());

    expect(operatorScopeRow()['assignment_category_ids'])->toBe([$campaignCategory->id]);
});

it('answers an empty assignment_category_ids when nothing demands a competence', function () {
    $campaign = operatorScopeCampaign(OperationalSite::factory()->create(), null);
    Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs(operatorScopeActor());

    expect(operatorScopeRow()['assignment_category_ids'])->toBe([]);
});

it('carries both keys on the row PATCH re-maps after an inline edit', function () {
    $site = OperationalSite::factory()->create();
    $category = operatorScopeCategory();
    $campaign = operatorScopeCampaign($site, $category);
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    $actor = operatorScopeActor(['leads.viewAny', 'leads.update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'operator',
        'value' => User::factory()->create()->id,
    ])->assertOk()
        ->assertJsonPath('data.assignment_site_id', $site->id)
        ->assertJsonPath('data.assignment_category_ids', [$category->id]);
});

// ---------------------------------------------------------------------------
// Batch: the page resolves once, whatever its size
// ---------------------------------------------------------------------------

it('resolves the whole page in one batch: the query count does not grow with the rows', function () {
    $site = OperationalSite::factory()->create();
    $campaign = operatorScopeCampaign($site, operatorScopeCategory());
    Sanctum::actingAs(operatorScopeActor());

    $measure = function (int $rows) use ($campaign, $site): int {
        Lead::query()->delete();
        // Same Sede on every row: an all-null `operational_site_id` would
        // skip the site/address/city eager loads and measure the fixture,
        // not the page resolution.
        Lead::factory()->count($rows)->create([
            'campaign_id' => $campaign->id,
            'operational_site_id' => $site->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson('/api/tables/leads/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    // First request of the process pays the permission/custom-field cache
    // warm-up: measuring it would compare fixtures, not the page resolution.
    $measure(1);

    expect($measure(20))->toBe($measure(1));
});
