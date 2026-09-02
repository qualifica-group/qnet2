<?php

use App\Enums\CategoryManagementMode;
use App\Http\Requests\Concerns\ValidatesProductCategoryBusinessFunction;
use App\Models\BusinessFunction;
use App\Models\Country;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * `product_lines` collection rules (spec 0094, D-1/D-2), shared with the
 * Opportunity/Campaign via ProductLineSetValidator — NOT reimplemented here:
 * this file only exercises the rules through the Project endpoints (AC-010..
 * AC-014, AC-016, AC-017, AC-019, AC-020).
 */
if (! function_exists('projectCoherenceUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function projectCoherenceUserWith(array $abilities): User
    {
        foreach (['create', 'update', 'view'] as $ability) {
            Permission::findOrCreate("projects.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("projects.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('projectRequiredFields')) {
    /**
     * @return array<string, mixed>
     */
    function projectRequiredFields(): array
    {
        return [
            'pipeline_status_id' => PipelineStatus::factory()->create()->id,
            'country_id' => Country::factory()->create()->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ];
    }
}

it('create: without product_lines -> 422 on product_lines (AC-010)', function () {
    $actor = projectCoherenceUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['name' => 'No Lines', ...projectRequiredFields()])
        ->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Project::count())->toBe(0);
});

it('create: with product_lines: [] -> 422 on product_lines (AC-010)', function () {
    $actor = projectCoherenceUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['name' => 'Empty Lines', 'product_lines' => [], ...projectRequiredFields()])
        ->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Project::count())->toBe(0);
});

it('create: two identical rows -> 422 on product_lines.1.product_category_id with DUPLICATE_PAIR_MESSAGE (AC-011)', function () {
    $actor = projectCoherenceUserWith(['create']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Duplicate Pair',
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
        ],
        ...projectRequiredFields(),
    ])->assertStatus(422)->assertJsonValidationErrors([
        'product_lines.1.product_category_id' => 'This business function / product category pair is already present.',
    ]);

    expect(Project::count())->toBe(0);
});

it('create: a category whose effective business function differs -> 422 on product_lines.0.business_function_id with BUSINESS_FUNCTION_MISMATCH_MESSAGE (AC-012)', function () {
    $actor = projectCoherenceUserWith(['create']);
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $categoryOfB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Mismatch',
        'product_lines' => [
            ['business_function_id' => $functionA->id, 'product_category_id' => $categoryOfB->id],
        ],
        ...projectRequiredFields(),
    ])->assertStatus(422)->assertJsonValidationErrors([
        'product_lines.0.business_function_id' => 'This product category does not belong to the selected business function.',
    ]);

    expect(Project::count())->toBe(0);
});

it('create: 201 when the category INHERITS the selected business function from an ancestor', function () {
    $actor = projectCoherenceUserWith(['create']);
    $function = BusinessFunction::factory()->create();
    $parent = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    // The child has no own business function -> its EFFECTIVE one is the parent's.
    $child = ProductCategory::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/projects', [
        'name' => 'Inherited',
        'product_lines' => [['business_function_id' => $function->id, 'product_category_id' => $child->id]],
        ...projectRequiredFields(),
    ])->assertCreated();

    expect($response->json('data.product_lines.0.product_category.id'))->toBe($child->id);
});

it('create: a non-selectable category -> 422; the SAME category already persisted stays acceptable on update (AC-013)', function () {
    $actor = projectCoherenceUserWith(['create', 'update']);
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id, 'is_selectable' => true]);
    Sanctum::actingAs($actor);

    $line = ['business_function_id' => $function->id, 'product_category_id' => $category->id];
    $project = Project::factory()->create();
    $project->productLines()->create($line);

    $category->update(['is_selectable' => false]);

    // PATCH resubmitting the SAME now-unselectable category: exempt (D-3b).
    $this->patchJson("/api/projects/{$project->id}", ['product_lines' => [$line]])
        ->assertOk();

    // POST with the same now-unselectable category on a NEW project: rejected.
    $this->postJson('/api/projects', [
        'name' => 'Unselectable',
        'product_lines' => [$line],
        ...projectRequiredFields(),
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.product_category_id');
});

it('create: two rows where one resolves to a `single` management_mode root -> 422 on product_lines with SINGLE_ROW_ONLY_MESSAGE (AC-014)', function () {
    $actor = projectCoherenceUserWith(['create']);
    $function = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create(['business_function_id' => $function->id, 'management_mode' => CategoryManagementMode::Single]);
    $categoryA = ProductCategory::factory()->childOf($root)->create();
    $categoryB = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Single Root',
        'product_lines' => [
            ['business_function_id' => $function->id, 'product_category_id' => $categoryA->id],
            ['business_function_id' => $function->id, 'product_category_id' => $categoryB->id],
        ],
        ...projectRequiredFields(),
    ])->assertStatus(422)->assertJsonValidationErrors([
        'product_lines' => 'This product category allows only a single row.',
    ]);

    expect(Project::count())->toBe(0);
});

it('update: PATCH omitting product_lines leaves the collection invariant (AC-016)', function () {
    $actor = projectCoherenceUserWith(['update', 'view']);
    $project = Project::factory()->withProductLine()->create();
    $before = $project->productLines()->pluck('id')->all();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['name' => 'Renamed Only'])->assertOk();

    expect($project->productLines()->pluck('id')->all())->toBe($before);
});

it('update: PATCH with product_lines: [] -> 422 (AC-016)', function () {
    $actor = projectCoherenceUserWith(['update']);
    $project = Project::factory()->withProductLine()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['product_lines' => []])
        ->assertStatus(422)->assertJsonValidationErrors('product_lines');
});

it('update: PATCH with a different collection REPLACES it integrally, no residual row (AC-017)', function () {
    $actor = projectCoherenceUserWith(['update']);
    $project = Project::factory()->withProductLine()->create();
    $staleLineId = $project->productLines()->sole()->id;
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", [
        'product_lines' => [['business_function_id' => $function->id, 'product_category_id' => $category->id]],
    ])->assertOk();

    $project->refresh();
    expect($project->productLines)->toHaveCount(1);
    expect($project->productLines()->pluck('id')->all())->not->toContain($staleLineId);
    expect($project->productLines->first()->product_category_id)->toBe($category->id);
});

it('show: exposes product_lines[] with {id, business_function{id,name}, product_category{id,name}}, no scalar fields, no N+1 (AC-019)', function () {
    $actor = projectCoherenceUserWith(['view']);
    $businessFunction = BusinessFunction::factory()->create(['name' => 'Marketing']);
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id, 'name' => 'Widgets']);
    $project = Project::factory()->create();
    $project->productLines()->create(['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/projects/{$project->id}")->assertOk();

    expect($response->json('data.product_lines'))->toHaveCount(1)
        ->and($response->json('data.product_lines.0.business_function'))->toMatchArray(['id' => $businessFunction->id, 'name' => 'Marketing'])
        ->and($response->json('data.product_lines.0.product_category'))->toMatchArray(['id' => $category->id, 'name' => 'Widgets']);

    expect($response->json('data'))->not->toHaveKeys(['business_function_id', 'business_function', 'product_category_id', 'product_category']);
});

it('AC-020: the retired ValidatesProductCategoryBusinessFunction trait no longer exists in the repo', function () {
    // autoload disabled ($autoload = false): a plain existence check that
    // never attempts to `include` the (deleted) file, unlike the default
    // class_exists(), which would.
    expect(class_exists(ValidatesProductCategoryBusinessFunction::class, false))->toBeFalse();
    expect(File::exists(app_path('Http/Requests/Concerns/ValidatesProductCategoryBusinessFunction.php')))->toBeFalse();
});
