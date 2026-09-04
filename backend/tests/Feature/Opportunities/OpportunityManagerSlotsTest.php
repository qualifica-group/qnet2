<?php

use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// `manager_slots` (spec 0020/0040, AC-015): the ordered, gap-aware pivot
// write path. Split out of OpportunityCrudTest (file-size limit,
// engineering.md §6). Cap raised 4 -> 12 by spec 0080 amendment A1
// (App\Support\ManagerPositions), AC-052.

uses(RefreshDatabase::class);

if (! function_exists('opportunityUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('mandatoryOpportunityFks')) {
    /**
     * @return array{registry_id: int, supervisor_id: int, product_lines: array<int, array{business_function_id: int, product_category_id: int}>, products_of_interest: array<int, int>}
     */
    function mandatoryOpportunityFks(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

it('create: manager_slots [u1, null, u2] -> pivot with position 1 and 3, gap preserved (AC-015)', function () {
    $actor = opportunityUserWith(['create']);
    $userOne = User::factory()->create();
    $userTwo = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Managed deal',
        'manager_slots' => [$userOne->id, null, $userTwo->id],
    ], mandatoryOpportunityFks()))->assertCreated();

    $opportunityId = $response->json('data.id');

    $this->assertDatabaseHas('opportunity_user', ['opportunity_id' => $opportunityId, 'user_id' => $userOne->id, 'position' => 1]);
    $this->assertDatabaseHas('opportunity_user', ['opportunity_id' => $opportunityId, 'user_id' => $userTwo->id, 'position' => 3]);
    expect($response->json('data.managers'))->toBe([
        ['id' => $userOne->id, 'name' => $userOne->name, 'position' => 1],
        ['id' => $userTwo->id, 'name' => $userTwo->name, 'position' => 3],
    ]);
});

it('create: a duplicate user across manager slots -> 422 (AC-015)', function () {
    $actor = opportunityUserWith(['create']);
    $user = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge([
        'name' => 'Duplicate manager',
        'manager_slots' => [$user->id, $user->id],
    ], mandatoryOpportunityFks()))->assertStatus(422)->assertJsonValidationErrors('manager_slots');
});

it('create: more than 12 filled manager slots -> 422 (AC-015, spec 0080 amendment A1)', function () {
    $actor = opportunityUserWith(['create']);
    $users = User::factory()->count(13)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge([
        'name' => 'Too many managers',
        'manager_slots' => $users->pluck('id')->all(),
    ], mandatoryOpportunityFks()))->assertStatus(422)->assertJsonValidationErrors('manager_slots');
});

it('create: more than 4 managers is now accepted, up to the 12 cap (AC-052)', function () {
    $actor = opportunityUserWith(['create']);
    $users = User::factory()->count(6)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Six managers',
        'manager_slots' => $users->pluck('id')->all(),
    ], mandatoryOpportunityFks()))->assertCreated();

    expect($response->json('data.managers'))->toHaveCount(6);
});

it('create: accepts exactly 12 filled manager_slots (AC-052)', function () {
    $actor = opportunityUserWith(['create']);
    $users = User::factory()->count(12)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Twelve managers',
        'manager_slots' => $users->pluck('id')->all(),
    ], mandatoryOpportunityFks()))->assertCreated();

    expect($response->json('data.managers'))->toHaveCount(12);
});

it('update: swapping two managers between slots is accepted, not a 500 on the (opportunity, position) unique constraint', function () {
    $actor = opportunityUserWith(['create', 'update']);
    $userOne = User::factory()->create();
    $userTwo = User::factory()->create();
    Sanctum::actingAs($actor);

    $opportunityId = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Swapped managers',
        'manager_slots' => [$userOne->id, $userTwo->id],
    ], mandatoryOpportunityFks()))->assertCreated()->json('data.id');

    $response = $this->putJson("/api/opportunities/{$opportunityId}", [
        'manager_slots' => [$userTwo->id, $userOne->id],
    ])->assertOk();

    expect($response->json('data.managers'))->toBe([
        ['id' => $userTwo->id, 'name' => $userTwo->name, 'position' => 1],
        ['id' => $userOne->id, 'name' => $userOne->name, 'position' => 2],
    ]);
});
