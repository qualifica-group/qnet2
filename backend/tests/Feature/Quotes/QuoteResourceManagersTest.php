<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0087 data_contract, GET /api/quotes/{quote}: `managers`/
 * `manager_labels`/`operator_id`/`managers_synchronized` are ADDITIVE —
 * every pre-existing key stays byte-for-byte the same (AC-013/AC-006-shape).
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteManagersActor')) {
    function quoteManagersActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('quotes.view');

        return $user;
    }
}

it('AC-013: manager_labels is a sparse map, NOT reindexed by #[PreserveKeys]', function () {
    $category = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore']]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create();
    QuoteLine::factory()->for($quote)->create(['product_id' => $product->id]);
    Sanctum::actingAs(quoteManagersActor());

    $response = $this->getJson("/api/quotes/{$quote->id}")->assertOk();

    expect($response->json('data.manager_labels'))->toBe(['2' => 'Operatore']);
});

it('show: managers/operator_id are additive, ordered by position, and supervisor stays untouched', function () {
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => User::factory()->create()->id]);
    $ga1 = User::factory()->create();
    $operator = User::factory()->create();
    $quote->managers()->sync([
        $operator->id => ['position' => ManagerPositions::OPERATOR],
        $ga1->id => ['position' => 1],
    ]);
    $quote->forceFill(['operator_id' => $operator->id])->save();
    Sanctum::actingAs(quoteManagersActor());

    $response = $this->getJson("/api/quotes/{$quote->id}")->assertOk();

    expect($response->json('data.managers.0.id'))->toBe($ga1->id)
        ->and($response->json('data.managers.0.position'))->toBe(1)
        ->and($response->json('data.managers.1.id'))->toBe($operator->id)
        ->and($response->json('data.managers.1.position'))->toBe(ManagerPositions::OPERATOR)
        ->and($response->json('data.operator_id'))->toBe($operator->id)
        ->and($response->json('data.supervisor_id'))->toBe($quote->supervisor_id);
});

it('show: manager_labels/managers are [] when unresolved / empty, never null', function () {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteManagersActor());

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.manager_labels', [])
        ->assertJsonPath('data.managers', [])
        ->assertJsonPath('data.operator_id', null);
});

it('show: managers_synchronized reflects D-7 (both category settings)', function () {
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'single_quote_per_opportunity' => true,
        'management_mode' => CategoryManagementMode::Single,
    ]);
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs(quoteManagersActor());

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.managers_synchronized', true);
});
