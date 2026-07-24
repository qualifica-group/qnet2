<?php

use App\Enums\StatusGroup;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardedReferentUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardedReferentUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("rewarded-referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("rewarded-referents.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('rewardForOpportunity')) {
    /**
     * A Reward whose origin is $opportunity (bypasses the factory's own
     * default nested Opportunity, D-3's `nested_sync_precedent` shape).
     *
     * @param  array<string, mixed>  $attributes
     */
    function rewardForOpportunity(Referent $referent, Opportunity $opportunity, array $attributes = []): Reward
    {
        return Reward::factory()->for($opportunity, 'source')->create([
            'referent_id' => $referent->id,
            ...$attributes,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-005 — only referents with >= 1 reward appear
// ---------------------------------------------------------------------------

it('rows: only referents holding at least one reward appear (AC-005)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $withReward = Referent::factory()->create();
    rewardForOpportunity($withReward, Opportunity::factory()->create());
    $alsoWithReward = Referent::factory()->create();
    rewardForOpportunity($alsoWithReward, Opportunity::factory()->create());
    Referent::factory()->create(); // no reward -> excluded

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    expect($response->json('items'))->toHaveCount(2)
        ->and($response->json('pagination.total'))->toBe(2)
        ->and(collect($response->json('items'))->pluck('id')->all())
        ->toEqualCanonicalizing([$withReward->id, $alsoWithReward->id]);
});

// ---------------------------------------------------------------------------
// AC-006 — one row per referent regardless of reward count, correct total
// ---------------------------------------------------------------------------

it('rows: a referent with 4 rewards yields ONE row with rewards_count = 4 (AC-006)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $referent = Referent::factory()->create();

    for ($i = 0; $i < 4; $i++) {
        rewardForOpportunity($referent, Opportunity::factory()->create());
    }

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $items = collect($response->json('items'));

    expect($items)->toHaveCount(1)
        ->and($items->first()['rewards_count'])->toBe(4);
});

// ---------------------------------------------------------------------------
// AC-007 — active/completed counters derived from opportunity_statuses.group (D-2)
// ---------------------------------------------------------------------------

it('rows: active_rewards_count/completed_rewards_count are derived from the origin status group (AC-007)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $referent = Referent::factory()->create();

    rewardForOpportunity($referent, Opportunity::factory()->create([
        'opportunity_status_id' => OpportunityStatus::factory()->group(StatusGroup::Open)->create()->id,
    ]));
    rewardForOpportunity($referent, Opportunity::factory()->create([
        'opportunity_status_id' => OpportunityStatus::factory()->group(StatusGroup::Pending)->create()->id,
    ]));
    rewardForOpportunity($referent, Opportunity::factory()->create([
        'opportunity_status_id' => OpportunityStatus::factory()->group(StatusGroup::Closed)->create()->id,
    ]));

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $referent->id);

    expect($row['rewards_count'])->toBe(3)
        ->and($row['active_rewards_count'])->toBe(2)
        ->and($row['completed_rewards_count'])->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-008 — last_assigned_at is the MAX assigned_at
// ---------------------------------------------------------------------------

it('rows: last_assigned_at is the MAXIMUM assigned_at across the referent\'s rewards (AC-008)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $referent = Referent::factory()->create();

    rewardForOpportunity($referent, Opportunity::factory()->create(), ['assigned_at' => '2026-01-10']);
    rewardForOpportunity($referent, Opportunity::factory()->create(), ['assigned_at' => '2026-06-15']);
    rewardForOpportunity($referent, Opportunity::factory()->create(), ['assigned_at' => '2026-03-01']);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $referent->id);

    expect($row['last_assigned_at'])->toBe('2026-06-15');
});

// ---------------------------------------------------------------------------
// AC-009 — global search extended to personalData.last_name (referent has no first/last name)
// ---------------------------------------------------------------------------

it('rows: search matches personalData.last_name even when referents.name does not contain it (AC-009)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $matching = Referent::factory()->withPersonalData(fn ($card) => $card->state(['last_name' => 'Bianchi', 'first_name' => 'Mario']))->create();
    rewardForOpportunity($matching, Opportunity::factory()->create());
    $other = Referent::factory()->create(['name' => 'Someone Else']);
    rewardForOpportunity($other, Opportunity::factory()->create());

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25, 'search' => 'Bianchi',
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids->all())->toBe([$matching->id]);
});

// ---------------------------------------------------------------------------
// AC-010 — sortModel on rewards_count respected; unknown colId -> 422
// ---------------------------------------------------------------------------

it('rows: sortModel on rewards_count desc orders items by the counter (AC-010)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    $low = Referent::factory()->create();
    rewardForOpportunity($low, Opportunity::factory()->create());
    $high = Referent::factory()->create();
    rewardForOpportunity($high, Opportunity::factory()->create());
    rewardForOpportunity($high, Opportunity::factory()->create());
    rewardForOpportunity($high, Opportunity::factory()->create());

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'rewards_count', 'sort' => 'desc']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$high->id, $low->id]);
});

it('rows: a colId outside sortableColumnIds() is rejected with 422 (AC-010)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/rewarded-referents/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'email', 'sort' => 'asc']],
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-013 — query count is CONSTANT regardless of N (referents) / M (rewards each)
// ---------------------------------------------------------------------------

/**
 * A query-count listener scoped to the domain tables this endpoint's grid
 * query + eager loads can touch (matched on the FROM clause, mirroring
 * RequestManagementWorkflowStatusOptionsPerRowTest's precedent): permission
 * checks (`permissions`/`roles`/`model_has_*`) never match, so their
 * caching behaviour across the two calls in the SAME test cannot skew the
 * count either way.
 */
if (! function_exists('countTableQueries')) {
    function countTableQueries(Closure $callback): int
    {
        $count = 0;
        $listener = function ($query) use (&$count): void {
            if (preg_match('/from ["`]?(referents|personal_data|contacts|referent_registry|registries|rewards|opportunities|opportunity_statuses)["`]?/i', $query->sql) === 1) {
                $count++;
            }
        };

        DB::listen($listener);
        $callback();

        return $count;
    }
}

it('rows: the query count is CONSTANT regardless of the number of referents/rewards (AC-013)', function () {
    $actor = rewardedReferentUserWith(['viewAny']);

    for ($i = 0; $i < 3; $i++) {
        $referent = Referent::factory()->create();
        rewardForOpportunity($referent, Opportunity::factory()->create());
    }

    Sanctum::actingAs($actor);
    $smallDatasetQueries = countTableQueries(function (): void {
        $this->postJson('/api/tables/rewarded-referents/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    });

    for ($i = 0; $i < 12; $i++) {
        $referent = Referent::factory()->create();
        rewardForOpportunity($referent, Opportunity::factory()->create());
        rewardForOpportunity($referent, Opportunity::factory()->create());
        rewardForOpportunity($referent, Opportunity::factory()->create());
    }

    $largeDatasetQueries = countTableQueries(function (): void {
        $this->postJson('/api/tables/rewarded-referents/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    });

    expect($largeDatasetQueries)->toBe($smallDatasetQueries);
});
