<?php

use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\OpportunityWorkflowStatus;
use App\Models\Referent;
use App\Models\RewardType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Every advanced filter narrows the referent list to those with AT LEAST one
// matching reward (spec 0059, `<advanced_filters>`) — one test per filter
// (AC-011), each creating a MATCHING referent and a NON-matching one, then
// asserting ONLY the former survives (an exact single-id list already proves
// exclusivity — no need for a separate negative assertion).

it('reward_type advanced filter restricts to referents with a matching reward type (AC-011)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $wantedType = RewardType::factory()->create();
    $otherType = RewardType::factory()->create();

    $matching = Referent::factory()->create();
    rewardForOpportunity($matching, Opportunity::factory()->create(), ['reward_type_id' => $wantedType->id]);
    $nonMatching = Referent::factory()->create();
    rewardForOpportunity($nonMatching, Opportunity::factory()->create(), ['reward_type_id' => $otherType->id]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['reward_type' => [$wantedType->id]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('opportunity advanced filter restricts to referents rewarded from that specific opportunity (AC-011)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $wantedOpportunity = Opportunity::factory()->create();
    $otherOpportunity = Opportunity::factory()->create();

    $matching = Referent::factory()->create();
    rewardForOpportunity($matching, $wantedOpportunity);
    $nonMatching = Referent::factory()->create();
    rewardForOpportunity($nonMatching, $otherOpportunity);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['opportunity' => $wantedOpportunity->id],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('opportunity_status advanced filter restricts to referents whose reward origin has that status (AC-011)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $wantedStatus = OpportunityStatus::factory()->create();
    $otherStatus = OpportunityStatus::factory()->create();

    $matching = Referent::factory()->create();
    rewardForOpportunity($matching, Opportunity::factory()->create(['opportunity_status_id' => $wantedStatus->id]));
    $nonMatching = Referent::factory()->create();
    rewardForOpportunity($nonMatching, Opportunity::factory()->create(['opportunity_status_id' => $otherStatus->id]));

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['opportunity_status' => [$wantedStatus->id]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('workflow_status advanced filter matches by NAME across different workflows (AC-011)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $wantedStatus = OpportunityWorkflowStatus::factory()->global()->create(['name' => 'In lavorazione']);
    $otherStatus = OpportunityWorkflowStatus::factory()->global()->create(['name' => 'Completata']);

    $matching = Referent::factory()->create();
    rewardForOpportunity($matching, Opportunity::factory()->create(['opportunity_workflow_status_id' => $wantedStatus->id]));
    $nonMatching = Referent::factory()->create();
    rewardForOpportunity($nonMatching, Opportunity::factory()->create(['opportunity_workflow_status_id' => $otherStatus->id]));

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['workflow_status' => ['In lavorazione']],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('operator advanced filter restricts to referents whose reward origin has that GA2 operator (AC-011)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $wantedOperator = User::factory()->create();
    $otherOperator = User::factory()->create();

    $matching = Referent::factory()->create();
    $matchingOpportunity = Opportunity::factory()->create();
    $matchingOpportunity->managers()->attach($wantedOperator->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
    rewardForOpportunity($matching, $matchingOpportunity);

    $nonMatching = Referent::factory()->create();
    $otherOpportunity = Opportunity::factory()->create();
    $otherOpportunity->managers()->attach($otherOperator->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
    rewardForOpportunity($nonMatching, $otherOpportunity);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['operator' => [$wantedOperator->id]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('assigned_at advanced filter restricts to referents with a reward assigned inside the date range (AC-011)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);

    $matching = Referent::factory()->create();
    rewardForOpportunity($matching, Opportunity::factory()->create(), ['assigned_at' => '2026-05-15']);
    $nonMatching = Referent::factory()->create();
    rewardForOpportunity($nonMatching, Opportunity::factory()->create(), ['assigned_at' => '2026-01-01']);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['assigned_at' => ['from' => '2026-05-01', 'to' => '2026-05-31']],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});
