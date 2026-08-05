<?php

use App\Enums\StatusSystemKey;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Reward;
use App\Models\RewardStatus;
use App\Models\RewardType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * BR-6/AC-019 (spec 0060): every reward created through
 * RewardAssignmentWriter::createAdded() starts on the system `pending` row,
 * resolved by `system_key` — never a hardcoded id. Self-contained helpers,
 * same isolation precedent as RewardAssignmentTest.php.
 */
uses(RefreshDatabase::class);

if (! function_exists('defaultStatusActor')) {
    function defaultStatusActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.create', 'opportunities.update']);

        return $user;
    }
}

if (! function_exists('defaultStatusBasePayload')) {
    /**
     * @return array<string, mixed>
     */
    function defaultStatusBasePayload(): array
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [$product->id],
        ];
    }
}

it('creates a reward with reward_status_id resolved to the system pending row, not hardcoded (BR-6/AC-019)', function () {
    $actor = defaultStatusActor();
    $reporter = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();

    // Extra, non-system statuses so a hardcoded id (e.g. 1) would not
    // accidentally pass — the pending row is NOT guaranteed to keep id 1.
    RewardStatus::factory()->count(3)->create();
    $pending = RewardStatus::query()->where('system_key', StatusSystemKey::Pending->value)->firstOrFail();

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(defaultStatusBasePayload(), [
        'reporter_id' => $reporter->id,
        'rewards' => [['reward_type_id' => $rewardType->id]],
    ]))->assertCreated();

    $reward = Reward::query()->where('source_id', $response->json('data.id'))->firstOrFail();

    expect($reward->reward_status_id)->toBe($pending->id)
        ->and($response->json('data.rewards.0.reward_type.id'))->toBe($rewardType->id);
});

it('a reward added by a later PATCH also starts on pending (BR-6)', function () {
    $actor = defaultStatusActor();
    $reporter = Referent::factory()->create();
    $typeA = RewardType::factory()->create();
    $typeB = RewardType::factory()->create();
    $pending = RewardStatus::query()->where('system_key', StatusSystemKey::Pending->value)->firstOrFail();

    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(defaultStatusBasePayload(), [
        'reporter_id' => $reporter->id,
        'rewards' => [['reward_type_id' => $typeA->id]],
    ]))->assertCreated();

    $opportunity = Opportunity::findOrFail($created->json('data.id'));

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'rewards' => [['reward_type_id' => $typeA->id], ['reward_type_id' => $typeB->id]],
    ])->assertOk();

    $newReward = Reward::query()->where('source_id', $opportunity->id)->where('reward_type_id', $typeB->id)->firstOrFail();

    expect($newReward->reward_status_id)->toBe($pending->id);
});
