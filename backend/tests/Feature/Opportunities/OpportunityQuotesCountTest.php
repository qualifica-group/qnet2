<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0067 (AC-019..022): the explicit inverse `Opportunity::quotes()`
 * relation plus the `quotes_count` field OpportunityResource exposes for the
 * Offerte panel's initial header value. No existing key changes name, type
 * or position (asserted alongside `attribute_values`/`applicable_attributes`
 * in OpportunityAttributeValuesResourceTest, same additive precedent).
 */
uses(RefreshDatabase::class);

if (! function_exists('opportunityQuotesCountViewer')) {
    function opportunityQuotesCountViewer(): User
    {
        Permission::findOrCreate('opportunities.view');
        Permission::findOrCreate('opportunities.delete');
        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.view', 'opportunities.delete']);

        return $user;
    }
}

it('GET opportunity with no quotes exposes quotes_count = 0 (AC-020)', function () {
    $actor = opportunityQuotesCountViewer();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/{$opportunity->id}")->assertOk();

    $response->assertJsonPath('data.quotes_count', 0);
    // No regression on the pre-existing shape.
    $response->assertJsonPath('data.id', $opportunity->id);
    $response->assertJsonPath('data.name', $opportunity->name);
});

it('GET opportunity with 3 quotes exposes quotes_count = 3 (AC-020)', function () {
    $actor = opportunityQuotesCountViewer();
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.quotes_count', 3);
});

it('quotes_count does not add an N+1 query as the number of quotes grows (AC-021)', function () {
    $actor = opportunityQuotesCountViewer();

    $small = Opportunity::factory()->create();
    Quote::factory()->for($small)->count(1)->create();

    $large = Opportunity::factory()->create();
    Quote::factory()->for($large)->count(10)->create();

    Sanctum::actingAs($actor);

    // Warm Spatie's permission cache and CustomFieldProvider's per-request
    // memo (App\CustomFields\CustomFieldProvider) before measuring, mirroring
    // RewardDetailEndpointTest's own precedent, so neither call absorbs a
    // size-unrelated, one-off query that has nothing to do with quotes_count.
    $actor->can('opportunities.view');
    $this->getJson("/api/opportunities/{$small->id}");

    DB::enableQueryLog();
    $this->getJson("/api/opportunities/{$small->id}")->assertOk()->assertJsonPath('data.quotes_count', 1);
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();

    $this->getJson("/api/opportunities/{$large->id}")->assertOk()->assertJsonPath('data.quotes_count', 10);
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

it('an opportunity with at least one quote cannot be deleted: 409, not deleted (AC-022)', function () {
    $actor = opportunityQuotesCountViewer();
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunities/{$opportunity->id}")->assertStatus(409);
    $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id]);
});

it('an opportunity without quotes still deletes cleanly (AC-022, no false positive)', function () {
    $actor = opportunityQuotesCountViewer();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunities/{$opportunity->id}")->assertNoContent();
    $this->assertDatabaseMissing('opportunities', ['id' => $opportunity->id]);
});
