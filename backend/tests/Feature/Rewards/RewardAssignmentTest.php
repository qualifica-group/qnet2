<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Reward;
use App\Models\RewardType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Reward assignments on the opportunities CRUD channel (spec 0059,
 * `<sync_semantics>`/D-3): the `rewards` nested payload, synced by
 * RewardAssignmentWriter — AC-004, AC-019, AC-020, AC-021, AC-022. AC-023
 * (identical semantics on the request-management channel) lives in
 * RewardAssignmentRequestManagementTest.php.
 *
 * Self-contained helpers (not reused across test files/directories): keeps
 * this suite runnable in isolation.
 */
uses(RefreshDatabase::class);

if (! function_exists('rewardAssignmentActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardAssignmentActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('rewardAssignmentCategory')) {
    function rewardAssignmentCategory(): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
    }
}

if (! function_exists('rewardAssignmentBasePayload')) {
    /**
     * The opportunities CRUD's own mandatory fields (registry_id,
     * opportunity_status_id, product_lines, products_of_interest) — none of
     * them relevant to `rewards` itself, just what StoreOpportunityRequest
     * requires to accept the request at all.
     *
     * @return array<string, mixed>
     */
    function rewardAssignmentBasePayload(): array
    {
        $category = rewardAssignmentCategory();
        $product = Product::factory()->create(['category_id' => $category->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'opportunity_status_id' => OpportunityStatus::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [$product->id],
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-019 — create persists reward rows and exposes them on the resource
// ---------------------------------------------------------------------------

it('create: rewards + reporter_id persists reward rows and is exposed on the resource (AC-019)', function () {
    $actor = rewardAssignmentActor(['create']);
    $reporter = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(rewardAssignmentBasePayload(), [
        'reporter_id' => $reporter->id,
        'rewards' => [['reward_type_id' => $rewardType->id]],
    ]))->assertCreated();

    $response->assertJsonCount(1, 'data.rewards')
        ->assertJsonPath('data.rewards.0.reward_type.id', $rewardType->id);

    $this->assertDatabaseHas('rewards', [
        'referent_id' => $reporter->id,
        'reward_type_id' => $rewardType->id,
        'source_type' => 'opportunity',
        'source_id' => $response->json('data.id'),
    ]);
    $persisted = Reward::query()->where('source_id', $response->json('data.id'))->firstOrFail();
    expect($persisted->assigned_at->toDateString())->toBe(now()->toDateString());
});

// ---------------------------------------------------------------------------
// AC-020 — authoritative replace: survivor's assigned_at untouched, [] clears
// everything, an absent key leaves the collection untouched
// ---------------------------------------------------------------------------

it('update: rewards replaces the set, keeps the survivor\'s assigned_at, [] clears all, absent leaves untouched (AC-020)', function () {
    $actor = rewardAssignmentActor(['create', 'update']);
    $reporter = Referent::factory()->create();
    $typeA = RewardType::factory()->create();
    $typeB = RewardType::factory()->create();
    $typeC = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(rewardAssignmentBasePayload(), [
        'reporter_id' => $reporter->id,
        'rewards' => [['reward_type_id' => $typeA->id], ['reward_type_id' => $typeB->id]],
    ]))->assertCreated();

    $opportunity = Opportunity::findOrFail($created->json('data.id'));
    $survivorAssignedAt = Reward::query()
        ->where('source_id', $opportunity->id)->where('reward_type_id', $typeA->id)
        ->firstOrFail()->assigned_at->toDateString();

    // Advance the clock so a bug that re-stamps assigned_at on every sync
    // would actually be caught (same-day resubmission would hide it).
    $this->travel(3)->days();

    // Remove B, add C: A survives with its ORIGINAL assigned_at.
    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'rewards' => [['reward_type_id' => $typeA->id], ['reward_type_id' => $typeC->id]],
    ])->assertOk()->assertJsonCount(2, 'data.rewards');

    $survivorRow = Reward::query()->where('source_id', $opportunity->id)->where('reward_type_id', $typeA->id)->firstOrFail();
    expect($survivorRow->assigned_at->toDateString())->toBe($survivorAssignedAt);
    expect(Reward::query()->where('source_id', $opportunity->id)->where('reward_type_id', $typeB->id)->exists())->toBeFalse();
    expect(Reward::query()->where('source_id', $opportunity->id)->where('reward_type_id', $typeC->id)->exists())->toBeTrue();

    // Absent key -> the collection is left completely untouched.
    $this->patchJson("/api/opportunities/{$opportunity->id}", ['success_probability' => 40])->assertOk();
    expect(Reward::query()->where('source_id', $opportunity->id)->count())->toBe(2);

    // `[]` -> authoritative clear.
    $this->patchJson("/api/opportunities/{$opportunity->id}", ['rewards' => []])
        ->assertOk()->assertJsonCount(0, 'data.rewards');
    expect(Reward::query()->where('source_id', $opportunity->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-021 — the two D-3 orphan guards
// ---------------------------------------------------------------------------

it('create: rewards non-empty with no reporter_id -> 422 on rewards, nothing created (AC-021)', function () {
    $actor = rewardAssignmentActor(['create']);
    $rewardType = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(rewardAssignmentBasePayload(), [
        'rewards' => [['reward_type_id' => $rewardType->id]],
    ]))->assertStatus(422)->assertJsonValidationErrors('rewards');

    expect(Opportunity::count())->toBe(0);
});

it('update: clearing reporter_id while rewards exist -> 422 on reporter_id, nothing changed (AC-021)', function () {
    $actor = rewardAssignmentActor(['create', 'update']);
    $reporter = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(rewardAssignmentBasePayload(), [
        'reporter_id' => $reporter->id,
        'rewards' => [['reward_type_id' => $rewardType->id]],
    ]))->assertCreated();

    $opportunity = Opportunity::findOrFail($created->json('data.id'));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['reporter_id' => null])
        ->assertStatus(422)->assertJsonValidationErrors('reporter_id');

    expect($opportunity->fresh()->reporter_id)->toBe($reporter->id)
        ->and(Reward::query()->where('source_id', $opportunity->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-022 — a changed reporter retargets every existing reward
// ---------------------------------------------------------------------------

it('update: changing reporter_id retargets every existing reward to the new Segnalatore (AC-022)', function () {
    $actor = rewardAssignmentActor(['create', 'update']);
    $originalReporter = Referent::factory()->create();
    $newReporter = Referent::factory()->create();
    $typeA = RewardType::factory()->create();
    $typeB = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(rewardAssignmentBasePayload(), [
        'reporter_id' => $originalReporter->id,
        'rewards' => [['reward_type_id' => $typeA->id], ['reward_type_id' => $typeB->id]],
    ]))->assertCreated();

    $opportunity = Opportunity::findOrFail($created->json('data.id'));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['reporter_id' => $newReporter->id])->assertOk();

    $referentIds = Reward::query()->where('source_id', $opportunity->id)->pluck('referent_id')->unique()->all();
    expect($referentIds)->toBe([$newReporter->id]);
});

// ---------------------------------------------------------------------------
// AC-004 — a duplicate reward_type_id in the same payload
// ---------------------------------------------------------------------------

it('create: a duplicate reward_type_id -> 422 on rewards.1.reward_type_id, nothing created (AC-004)', function () {
    $actor = rewardAssignmentActor(['create']);
    $rewardType = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(rewardAssignmentBasePayload(), [
        'reporter_id' => Referent::factory()->create()->id,
        'rewards' => [
            ['reward_type_id' => $rewardType->id],
            ['reward_type_id' => $rewardType->id],
        ],
    ]))->assertStatus(422)->assertJsonValidationErrors('rewards.1.reward_type_id');

    expect(Opportunity::count())->toBe(0);
});
