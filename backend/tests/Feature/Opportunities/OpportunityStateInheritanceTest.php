<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\City;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('stateInheritanceUserWith')) {
    /**
     * @param  array<int, string>  $opportunityAbilities
     * @param  array<int, string>  $leadAbilities
     */
    function stateInheritanceUserWith(array $opportunityAbilities, array $leadAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($opportunityAbilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        foreach ($leadAbilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('siteWithRegionForInheritance')) {
    function siteWithRegionForInheritance(State $state): OperationalSite
    {
        $city = City::factory()->forState($state)->create();

        return OperationalSite::factory()->withAddress($city)->create();
    }
}

// ---------------------------------------------------------------------------
// AC-002 (spec 0047) — conversion inherits state_id from the lead
// ---------------------------------------------------------------------------

it('conversion inherits state_id from the lead onto the created opportunity (AC-002)', function () {
    $actor = stateInheritanceUserWith(['create', 'view'], ['create']);
    $registry = Registry::factory()->create();
    $businessFunction = BusinessFunction::factory()->create();
    $productCategory = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $campaign = Campaign::factory()->create([
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $productCategory->id,
    ]);
    $operator = User::factory()->create();
    $state = State::factory()->create();
    $site = siteWithRegionForInheritance($state);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'operator_id' => $operator->id,
        'operational_site_id' => $site->id,
        'convert_to_opportunity' => true,
    ])->assertCreated();

    expect($response->json('data.state_id'))->toBe($state->id);

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->sole();
    expect($opportunity->state_id)->toBe($state->id);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.state_id', $state->id)
        ->assertJsonPath('data.state', ['id' => $state->id, 'name' => $state->name]);
});
