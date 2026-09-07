<?php

use App\Models\City;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0048 (A) + spec 0103 (D-1): GET /api/users/for-select gains an
 * optional `operational_site_id` filter, widened by 0103 to match a user
 * whose employment profile holds that Sede as PHYSICAL *or* REMOTE on the
 * `employment_profile_operational_site` pivot, plus a Sede `meta` on every
 * item that ALWAYS names the physical one (AC-011), regardless of which
 * membership matched the filter. Distinct file from UserForSelectTest.php
 * (existing for-select coverage, untouched).
 */
if (! function_exists('siteFilterActor')) {
    function siteFilterActor(): User
    {
        Permission::findOrCreate('users.viewAny');
        $actor = User::factory()->create();
        $actor->givePermissionTo('users.viewAny');

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — no operational_site_id: unchanged (every user, meta only when the
// operator actually has a physical Sede).
// ---------------------------------------------------------------------------

it('AC-001: without operational_site_id, every user is returned and meta is absent without a Sede', function () {
    $actor = siteFilterActor();
    $noEmployment = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $noEmployment->id);

    expect($item)->not->toBeNull()
        ->and(array_key_exists('meta', $item))->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-002 / AC-010 — operational_site_id restricts the list to users employed
// at that Sede, physical OR remote.
// ---------------------------------------------------------------------------

it('AC-002: operational_site_id restricts the list to users employed at that Sede', function () {
    $actor = siteFilterActor();
    $siteA = OperationalSite::factory()->withAddress()->create();
    $siteB = OperationalSite::factory()->withAddress()->create();
    $atSiteA = User::factory()->create();
    EmploymentProfile::factory()->physicalSite($siteA)->create(['user_id' => $atSiteA->id]);
    $atSiteB = User::factory()->create();
    EmploymentProfile::factory()->physicalSite($siteB)->create(['user_id' => $atSiteB->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/for-select?operational_site_id={$siteA->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($atSiteA->id)
        ->and($ids)->not->toContain($atSiteB->id)
        ->and($ids)->not->toContain($actor->id);
});

it('AC-002: rejects an operational_site_id that does not exist (422)', function () {
    $actor = siteFilterActor();
    Sanctum::actingAs($actor);

    $this->getJson('/api/users/for-select?operational_site_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('operational_site_id');
});

it('AC-010: a user with the Sede as PHYSICAL and one with it as REMOTE both appear', function () {
    $actor = siteFilterActor();
    $siteX = OperationalSite::factory()->withAddress()->create();
    $otherSite = OperationalSite::factory()->withAddress()->create();

    $physicalOperator = User::factory()->create();
    EmploymentProfile::factory()->physicalSite($siteX)->create(['user_id' => $physicalOperator->id]);

    $remoteOperator = User::factory()->create();
    EmploymentProfile::factory()
        ->physicalSite($otherSite)
        ->remoteSites($siteX)
        ->create(['user_id' => $remoteOperator->id]);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/for-select?operational_site_id={$siteX->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($physicalOperator->id)
        ->and($ids)->toContain($remoteOperator->id);
});

// ---------------------------------------------------------------------------
// AC-003 / AC-011 — meta.operational_site_id + composed label always names
// the PHYSICAL Sede, even when the filter matched a REMOTE membership.
// ---------------------------------------------------------------------------

it('AC-003: an operator with a physical Sede exposes meta {operational_site_id, operational_site_label}', function () {
    $actor = siteFilterActor();
    $city = City::factory()->create(['name' => 'Springfield']);
    $site = OperationalSite::factory()->withAddress($city)->create();
    $operator = User::factory()->create();
    EmploymentProfile::factory()->physicalSite($site)->create(['user_id' => $operator->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $operator->id);
    $address = $site->addresses()->first();

    expect($item['meta'])->toBe([
        'operational_site_id' => $site->id,
        'operational_site_label' => "{$address->line1} - Springfield",
    ]);
});

it('AC-003: an operator without a Sede omits meta entirely', function () {
    $actor = siteFilterActor();
    $operator = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $operator->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $operator->id);

    expect(array_key_exists('meta', $item))->toBeFalse();
});

it('AC-011: filtering by a REMOTE Sede still reports the operator\'s PHYSICAL Sede in meta', function () {
    $actor = siteFilterActor();
    $physicalCity = City::factory()->create(['name' => 'Physicalville']);
    $siteY = OperationalSite::factory()->withAddress($physicalCity)->create();
    $siteX = OperationalSite::factory()->withAddress()->create();

    $operator = User::factory()->create();
    EmploymentProfile::factory()
        ->physicalSite($siteY)
        ->remoteSites($siteX)
        ->create(['user_id' => $operator->id]);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/for-select?operational_site_id={$siteX->id}")->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $operator->id);

    expect($item['meta']['operational_site_id'])->toBe($siteY->id);
});

it('AC-011: an operator with ONLY a remote Sede (no physical) omits meta entirely', function () {
    $actor = siteFilterActor();
    $siteX = OperationalSite::factory()->withAddress()->create();

    $operator = User::factory()->create();
    EmploymentProfile::factory()->remoteSites($siteX)->create(['user_id' => $operator->id]);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/for-select?operational_site_id={$siteX->id}")->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $operator->id);

    expect($item)->not->toBeNull()
        ->and(array_key_exists('meta', $item))->toBeFalse();
});
