<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * `product_lines` collection rules (spec 0094, D-1/D-2) for a STANDALONE
 * campaign: the SAME shared rules Projects/Opportunities use
 * (ProductLineSetValidator, NOT reimplemented here). A LINKED campaign
 * derives its classification from the project (`product_lines` prohibited,
 * BR-2), so the check never applies to it — covered by CampaignCrudTest's
 * own linked-campaign tests (AC-018).
 */
if (! function_exists('campaignCoherenceUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function campaignCoherenceUserWith(array $abilities): User
    {
        foreach (['create', 'update'] as $ability) {
            Permission::findOrCreate("campaigns.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("campaigns.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('campaignRequiredFields')) {
    /**
     * @return array<string, mixed>
     */
    function campaignRequiredFields(): array
    {
        return [
            'project_id' => null,
            'country_id' => Country::factory()->create()->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ];
    }
}

it('create: standalone without product_lines -> 422 on product_lines (AC-015 mirrors AC-010)', function () {
    $actor = campaignCoherenceUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', ['name' => 'No Lines', ...campaignRequiredFields()])
        ->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Campaign::count())->toBe(0);
});

it('create: standalone with two identical rows -> 422 with DUPLICATE_PAIR_MESSAGE (AC-015 mirrors AC-011)', function () {
    $actor = campaignCoherenceUserWith(['create']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Duplicate Pair',
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
        ],
        ...campaignRequiredFields(),
    ])->assertStatus(422)->assertJsonValidationErrors([
        'product_lines.1.product_category_id' => 'This business function / product category pair is already present.',
    ]);

    expect(Campaign::count())->toBe(0);
});

it('create: standalone 422 when the product category belongs to a different business function (AC-015 mirrors AC-012)', function () {
    $actor = campaignCoherenceUserWith(['create']);
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $categoryOfB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Mismatch',
        'product_lines' => [
            ['business_function_id' => $functionA->id, 'product_category_id' => $categoryOfB->id],
        ],
        ...campaignRequiredFields(),
    ])->assertStatus(422)->assertJsonValidationErrors([
        'product_lines.0.business_function_id' => 'This product category does not belong to the selected business function.',
    ]);

    expect(Campaign::count())->toBe(0);
});

it('create: standalone 201 when the category INHERITS the business function from an ancestor', function () {
    $actor = campaignCoherenceUserWith(['create']);
    $function = BusinessFunction::factory()->create();
    $parent = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $child = ProductCategory::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/campaigns', [
        'name' => 'Inherited',
        'product_lines' => [['business_function_id' => $function->id, 'product_category_id' => $child->id]],
        'pipeline_status_id' => PipelineStatus::factory()->create()->id,
        ...campaignRequiredFields(),
    ])->assertCreated();

    expect($response->json('data.product_lines.0.product_category.id'))->toBe($child->id);
});

it('create: standalone non-selectable category -> 422; the SAME category already persisted stays acceptable on update (AC-015 mirrors AC-013)', function () {
    $actor = campaignCoherenceUserWith(['create', 'update']);
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id, 'is_selectable' => true]);
    Sanctum::actingAs($actor);

    $line = ['business_function_id' => $function->id, 'product_category_id' => $category->id];
    $campaign = Campaign::factory()->create();
    $campaign->productLines()->delete();
    $campaign->productLines()->create($line);

    $category->update(['is_selectable' => false]);

    // PATCH resubmitting the SAME now-unselectable category: exempt (D-3b).
    $this->patchJson("/api/campaigns/{$campaign->id}", ['product_lines' => [$line]])
        ->assertOk();

    // POST with the same now-unselectable category on a NEW standalone campaign: rejected.
    $this->postJson('/api/campaigns', [
        'name' => 'Unselectable',
        'product_lines' => [$line],
        ...campaignRequiredFields(),
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.product_category_id');

    expect(Campaign::count())->toBe(1);
});

it('create: standalone two rows where one resolves to a `single` management_mode root -> 422 with SINGLE_ROW_ONLY_MESSAGE (AC-015 mirrors AC-014)', function () {
    $actor = campaignCoherenceUserWith(['create']);
    $function = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create(['business_function_id' => $function->id, 'management_mode' => CategoryManagementMode::Single]);
    $categoryA = ProductCategory::factory()->childOf($root)->create();
    $categoryB = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Single Root',
        'product_lines' => [
            ['business_function_id' => $function->id, 'product_category_id' => $categoryA->id],
            ['business_function_id' => $function->id, 'product_category_id' => $categoryB->id],
        ],
        ...campaignRequiredFields(),
    ])->assertStatus(422)->assertJsonValidationErrors([
        'product_lines' => 'This product category allows only a single row.',
    ]);

    expect(Campaign::count())->toBe(0);
});

it('update: standalone 422 when changing to a product category of a different business function', function () {
    $actor = campaignCoherenceUserWith(['update']);
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $categoryOfA = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    $categoryOfB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);
    $campaign = Campaign::factory()->create();
    $campaign->productLines()->delete();
    $campaign->productLines()->create(['business_function_id' => $functionA->id, 'product_category_id' => $categoryOfA->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$campaign->id}", [
        'product_lines' => [['business_function_id' => $functionA->id, 'product_category_id' => $categoryOfB->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.business_function_id');
});

it('AC-018 (mirrors AC-020): POST linked campaign with product_lines sent -> 422 prohibited; response exposes the project rows', function () {
    $actor = campaignCoherenceUserWith(['create']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $project = Project::factory()->create();
    $project->productLines()->create(['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Linked With Lines',
        'project_id' => $project->id,
        'product_lines' => [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]],
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Campaign::count())->toBe(0);
});
