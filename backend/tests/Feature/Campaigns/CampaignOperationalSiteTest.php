<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\OperationalSite;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// `operational_site_id` (sede inheritance cascade project -> campaign -> lead):
// a PREFILL MODIFIABLE field, never a read-through — no server-side
// inheritance/lock, freely editable at every level, and the campaign's OWN
// value (never derived from the linked project, unlike the BR-2/BR-5 fields).
// Extracted out of CampaignCrudTest.php (file-size split, engineering.md §6).

if (! function_exists('campaignUserWith')) {
    /**
     * Local copy mirroring CampaignCrudTest's (each test file guards its own,
     * since file load order across the suite is not guaranteed).
     *
     * @param  array<int, string>  $abilities
     */
    function campaignUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("campaigns.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("campaigns.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('standaloneClassificationFields')) {
    /**
     * @return array<string, mixed>
     */
    function standaloneClassificationFields(): array
    {
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'pipeline_status_id' => PipelineStatus::factory()->create()->id,
            'product_lines' => [[
                'business_function_id' => $businessFunction->id,
                'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
            ]],
        ];
    }
}

if (! function_exists('campaignStoreDates')) {
    /**
     * @return array<string, string>
     */
    function campaignStoreDates(): array
    {
        return ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'];
    }
}

if (! function_exists('standaloneCampaignFields')) {
    /**
     * @return array<string, mixed>
     */
    function standaloneCampaignFields(): array
    {
        return array_merge(
            standaloneClassificationFields(),
            ['country_id' => Country::factory()->create()->id],
            campaignStoreDates(),
        );
    }
}

it('create: persists operational_site_id and exposes the composed operational_site label', function () {
    $actor = campaignUserWith(['create']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Roma 1', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $fields = standaloneCampaignFields();
    $response = $this->postJson('/api/campaigns', array_merge(
        ['name' => 'With Sede', 'operational_site_id' => $site->id],
        $fields,
    ))->assertCreated()
        ->assertJsonPath('data.operational_site_id', $site->id)
        ->assertJsonPath('data.operational_site.label', 'Via Roma 1');

    $this->assertDatabaseHas('campaigns', ['id' => $response->json('data.id'), 'operational_site_id' => $site->id]);
});

it('create: a non-existent operational_site_id -> 422 (no row persisted)', function () {
    $actor = campaignUserWith(['create']);
    Sanctum::actingAs($actor);

    $fields = standaloneCampaignFields();
    $this->postJson('/api/campaigns', array_merge(
        ['name' => 'Bad Sede', 'operational_site_id' => 999999],
        $fields,
    ))->assertStatus(422)->assertJsonValidationErrors('operational_site_id');

    expect(Campaign::count())->toBe(0);
});

it('update: sets operational_site_id on a campaign that had none, no server-side forcing', function () {
    $actor = campaignUserWith(['update']);
    $campaign = Campaign::factory()->create(['operational_site_id' => null]);
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['operational_site_id' => $site->id])
        ->assertOk()
        ->assertJsonPath('data.operational_site_id', $site->id);

    $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'operational_site_id' => $site->id]);
});

it('for-select: meta.operational_site is {id, label}', function () {
    $actor = campaignUserWith(['viewAny']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Verdi 5', 'is_primary' => true]);
    $campaign = Campaign::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/campaigns/for-select')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $campaign->id);

    expect($item['meta']['operational_site'])->toMatchArray([
        'id' => $site->id,
        'label' => 'Via Verdi 5',
    ]);
});

it('for-select: meta.operational_site is null when the campaign has no sede', function () {
    $actor = campaignUserWith(['viewAny']);
    $campaign = Campaign::factory()->create(['operational_site_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/campaigns/for-select')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $campaign->id);

    expect($item['meta']['operational_site'])->toBeNull();
});
