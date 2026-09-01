<?php

use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0092 — the write path of the `product_category_branch_id` criterion.
// Nothing in the payload shape changed: these assert that the new field rides
// the EXISTING allow-list / exists / signature machinery unmodified.
uses(RefreshDatabase::class);

if (! function_exists('quoteWorkflowUserWith')) {
    /**
     * Signature MUST stay identical to the same-named helper in
     * QuoteWorkflowCrudTest.php: whichever file Pest loads first wins the
     * definition (function_exists guard).
     *
     * @param  array<int, string>  $abilities
     */
    function quoteWorkflowUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quote-workflows.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quote-workflows.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('branchCriterionPayload')) {
    /**
     * @param  array<int, array{field: string, value_id: int}>  $criteria
     * @return array<string, mixed>
     */
    function branchCriterionPayload(array $criteria, string $name = 'Consulenza'): array
    {
        return [
            'name' => $name,
            'is_active' => true,
            'criteria' => $criteria,
            'statuses' => [
                ['name' => 'In lavorazione', 'color' => 'blue', 'group' => 'open'],
            ],
        ];
    }
}

it('accepts a branch criterion pointing at a NON-selectable container category (AC-020)', function () {
    $container = ProductCategory::factory()->create(['parent_id' => null, 'is_selectable' => false]);
    ProductCategory::factory()->create(['parent_id' => $container->id]);

    Sanctum::actingAs(quoteWorkflowUserWith(['create']));

    $response = $this->postJson('/api/quote-workflows', branchCriterionPayload([
        ['field' => 'product_category_branch_id', 'value_id' => $container->id],
    ]))->assertCreated();

    $workflow = QuoteWorkflow::findOrFail($response->json('data.id'));

    expect($workflow->criteria)->toHaveCount(1)
        ->and($workflow->criteria->first()->field)->toBe('product_category_branch_id')
        ->and($workflow->criteria->first()->value_id)->toBe($container->id);
});

it('rejects a branch criterion whose value_id is not a product category (422, AC-021)', function () {
    Sanctum::actingAs(quoteWorkflowUserWith(['create']));

    $this->postJson('/api/quote-workflows', branchCriterionPayload([
        ['field' => 'product_category_branch_id', 'value_id' => 999999],
    ]))->assertStatus(422)->assertJsonValidationErrors('criteria.0.value_id');
});

it('allows the exact-category and branch criteria together on one workflow (AC-022)', function () {
    $root = ProductCategory::factory()->create(['parent_id' => null]);
    $leaf = ProductCategory::factory()->create(['parent_id' => $root->id]);

    Sanctum::actingAs(quoteWorkflowUserWith(['create']));

    $response = $this->postJson('/api/quote-workflows', branchCriterionPayload([
        ['field' => 'product_category_id', 'value_id' => $leaf->id],
        ['field' => 'product_category_branch_id', 'value_id' => $root->id],
    ]))->assertCreated();

    expect(QuoteWorkflow::findOrFail($response->json('data.id'))->criteria->pluck('field')->sort()->values()->all())
        ->toBe(['product_category_branch_id', 'product_category_id']);
});

it('rejects the same branch criterion twice in one payload (422, AC-022)', function () {
    $root = ProductCategory::factory()->create(['parent_id' => null]);
    ProductCategory::factory()->create(['parent_id' => $root->id]);

    Sanctum::actingAs(quoteWorkflowUserWith(['create']));

    $this->postJson('/api/quote-workflows', branchCriterionPayload([
        ['field' => 'product_category_branch_id', 'value_id' => $root->id],
        ['field' => 'product_category_branch_id', 'value_id' => $root->id],
    ]))->assertStatus(422)->assertJsonValidationErrors('criteria.1.field');
});

it('folds the new field into the criteria-combination uniqueness (422, AC-023)', function () {
    $root = ProductCategory::factory()->create(['parent_id' => null]);
    ProductCategory::factory()->create(['parent_id' => $root->id]);

    Sanctum::actingAs(quoteWorkflowUserWith(['create']));

    $payload = branchCriterionPayload([
        ['field' => 'product_category_branch_id', 'value_id' => $root->id],
    ]);

    $this->postJson('/api/quote-workflows', $payload)->assertCreated();
    $this->postJson('/api/quote-workflows', [...$payload, 'name' => 'Consulenza bis'])->assertStatus(422);
});

it('resolves the criterion value label to the category name (AC-024)', function () {
    $root = ProductCategory::factory()->create(['name' => 'Consulenza', 'parent_id' => null]);
    ProductCategory::factory()->create(['parent_id' => $root->id]);

    Sanctum::actingAs(quoteWorkflowUserWith(['create', 'view']));

    $created = $this->postJson('/api/quote-workflows', branchCriterionPayload([
        ['field' => 'product_category_branch_id', 'value_id' => $root->id],
    ]))->assertCreated();

    $response = $this->getJson("/api/quote-workflows/{$created->json('data.id')}")->assertOk();

    expect($response->json('data.criteria.0.value_label'))->toBe('Consulenza');
});
