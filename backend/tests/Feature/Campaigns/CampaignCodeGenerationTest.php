<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// `code` (CMP-0001..., spec 0025, BR-1): manual-on-create with a sequential
// server-side fallback, plus the GET /api/campaigns/next-code auto-fill
// suggestion. Extracted out of CampaignCrudTest.php (file-size split,
// engineering.md §6).

if (! function_exists('campaignUserWith')) {
    /**
     * Local copy mirroring CampaignCrudTest's (each test file guards its
     * own, since file load order across the suite is not guaranteed).
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

if (! function_exists('campaignCoherentProductLine')) {
    /**
     * Local copy mirroring CampaignCrudTest's.
     *
     * @return array{business_function_id: int, product_category_id: int}
     */
    function campaignCoherentProductLine(): array
    {
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'business_function_id' => $businessFunction->id,
            'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
        ];
    }
}

if (! function_exists('standaloneClassificationFields')) {
    /**
     * Local copy mirroring CampaignCrudTest's.
     *
     * @return array<string, mixed>
     */
    function standaloneClassificationFields(): array
    {
        return [
            'pipeline_status_id' => PipelineStatus::factory()->create()->id,
            'product_lines' => [campaignCoherentProductLine()],
        ];
    }
}

if (! function_exists('campaignStoreDates')) {
    /**
     * Local copy mirroring CampaignCrudTest's.
     *
     * @return array<string, string>
     */
    function campaignStoreDates(): array
    {
        return ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'];
    }
}

if (! function_exists('standaloneCampaignFields')) {
    /**
     * Local copy mirroring CampaignCrudTest's.
     *
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

// ---------------------------------------------------------------------------
// spec 0025 (AC-001..AC-009 for campaigns, mirroring projects) — `code`
// manual-on-create, replacing the former AC-029 "explicit code is ignored".
// ---------------------------------------------------------------------------

it('create: no `code` in the payload -> code is server-generated CMP-0001 (AC-001/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'No Code'], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'CMP-0001');
});

it('create: an explicit `code` in the payload is persisted as-is (AC-002/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge([
        'name' => 'Manual Code',
        'code' => 'ACME-CMP-2026',
    ], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'ACME-CMP-2026');

    $this->assertDatabaseHas('campaigns', ['code' => 'ACME-CMP-2026']);
});

it('create: `code` as an empty string -> code is server-generated (AC-003/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'Empty Code', 'code' => ''], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'CMP-0001');
});

it('create: a duplicate `code` -> 422 on the `code` field (AC-004/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Campaign::factory()->create(['code' => 'ACME-CMP-2026']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge([
        'name' => 'Duplicate Code',
        'code' => 'ACME-CMP-2026',
    ], $fields))
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

it('create: a `code` of 33+ characters -> 422 (AC-005/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge([
        'name' => 'Too Long',
        'code' => str_repeat('A', 33),
    ], $fields))
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

it('create: a manual non-CMP code does not break the sequential generator (AC-006/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge([
        'name' => 'Manual First',
        'code' => 'ACME-CMP-2026',
    ], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'ACME-CMP-2026');

    $this->postJson('/api/campaigns', array_merge(['name' => 'Generated Second'], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'CMP-0001');
});

it('update: a `code` different from the persisted one -> 422, code unchanged (AC-007/AC-008)', function () {
    $actor = campaignUserWith(['update']);
    $campaign = Campaign::factory()->create(['code' => 'CMP-0001']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['code' => 'CMP-9999'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'code' => 'CMP-0001']);
});

// ---------------------------------------------------------------------------
// next-code — GET /api/campaigns/next-code (spec 0025, auto-fill suggestion)
// ---------------------------------------------------------------------------

it('next-code: suggests CMP-0001 on an empty table, then the following sequence', function () {
    Sanctum::actingAs(campaignUserWith(['create']));

    $this->getJson('/api/campaigns/next-code')
        ->assertOk()->assertJsonPath('data.code', 'CMP-0001');

    Campaign::factory()->create(['code' => 'CMP-0004']);

    $this->getJson('/api/campaigns/next-code')
        ->assertOk()->assertJsonPath('data.code', 'CMP-0005');
});

it('next-code: 403 without campaigns.create', function () {
    Sanctum::actingAs(campaignUserWith(['viewAny']));

    $this->getJson('/api/campaigns/next-code')->assertForbidden();
});
