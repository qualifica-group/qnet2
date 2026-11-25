<?php

use App\DataObjects\Commissions\CommissionResolutionContext;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteLineCommission;
use App\Models\User;
use App\Services\Commissions\CommissionRuleResolver;
use App\Services\Deletion\UsageMap;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0430: a rule points at several categories or several products through pivots.

function multiScopeActor(): User
{
    $actor = User::factory()->create();
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'viewActivity'] as $ability) {
        $actor->givePermissionTo(Permission::findOrCreate("commission-configurations.{$ability}"));
    }

    return $actor;
}

/** @return array<string, mixed> */
function multiScopePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Grouped rule',
        'recipient_role' => 'COMMERCIAL',
        'application_scope' => 'PRODUCT_CATEGORY',
        'commission_type' => 'PERCENTAGE',
        'value' => 5,
        'priority' => 0,
        'valid_from' => '2026-01-01',
        'status' => 'ACTIVE',
    ], $overrides);
}

/** @return list<int> */
function multiScopeWinners(Product $product): array
{
    return array_map(
        static fn ($match): int => $match->configurationId,
        app(CommissionRuleResolver::class)->resolve(new CommissionResolutionContext(
            productId: $product->id,
            productCategoryId: $product->category_id,
            roles: [CommissionRecipientRole::Commercial],
            referenceDate: new DateTimeImmutable('2026-07-29'),
        )),
    );
}

it('AC-001 applies a category rule to every listed category branch and to nothing else', function () {
    Sanctum::actingAs(multiScopeActor());
    [$a, $b, $c] = ProductCategory::factory()->count(3)->create();
    $childOfB = ProductCategory::factory()->create(['parent_id' => $b->id]);

    $id = $this->postJson('/api/commission-configurations', multiScopePayload(['product_category_ids' => [$a->id, $b->id]]))
        ->assertCreated()
        ->assertJsonPath('data.product_category_ids', [$a->id, $b->id])
        ->assertJsonPath('data.product_ids', [])
        ->json('data.id');

    expect(multiScopeWinners(Product::factory()->create(['category_id' => $a->id])))->toBe([$id])
        ->and(multiScopeWinners(Product::factory()->create(['category_id' => $childOfB->id])))->toBe([$id])
        ->and(multiScopeWinners(Product::factory()->create(['category_id' => $c->id])))->toBe([]);
});

it('AC-002 applies a product rule to every listed product, and beats a category rule', function () {
    Sanctum::actingAs(multiScopeActor());
    $category = ProductCategory::factory()->create();
    [$p1, $p2, $p3] = Product::factory()->count(3)->create(['category_id' => $category->id]);
    CommissionConfiguration::factory()->forCategories([$category->id])->create(['priority' => 999, 'valid_from' => '2026-01-01']);

    $id = $this->postJson('/api/commission-configurations', multiScopePayload([
        'application_scope' => 'PRODUCT',
        'product_ids' => [$p1->id, $p2->id],
    ]))->assertCreated()
        ->assertJsonCount(2, 'data.products')
        ->json('data.id');

    expect(multiScopeWinners($p1))->toBe([$id])
        ->and(multiScopeWinners($p2))->toBe([$id])
        ->and(multiScopeWinners($p3))->not->toContain($id);
});

it('AC-003 picks the closest category even when a farther rule has a higher priority', function () {
    $parent = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    $other = ProductCategory::factory()->create();
    CommissionConfiguration::factory()->forCategories([$parent->id])->create(['priority' => 100, 'valid_from' => '2026-01-01']);
    $closest = CommissionConfiguration::factory()->forCategories([$child->id, $other->id])->create(['priority' => 1, 'valid_from' => '2026-01-01']);

    expect(multiScopeWinners(Product::factory()->create(['category_id' => $child->id])))->toBe([$closest->id]);
});

it('AC-004 validates the lists, accepts inactive categories and exempts the saved products', function () {
    Sanctum::actingAs(multiScopeActor());
    $category = ProductCategory::factory()->create();
    $inactive = ProductCategory::factory()->create(['is_active' => false]);
    $product = Product::factory()->create();

    $this->postJson('/api/commission-configurations', multiScopePayload(['product_category_ids' => []]))
        ->assertUnprocessable()->assertJsonValidationErrors('product_category_ids');
    $this->postJson('/api/commission-configurations', multiScopePayload(['product_category_ids' => [$category->id, $category->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('product_category_ids.0');
    $this->postJson('/api/commission-configurations', multiScopePayload(['product_category_ids' => [$category->id, 999999]]))
        ->assertUnprocessable()->assertJsonValidationErrors('product_category_ids.1');
    $this->postJson('/api/commission-configurations', multiScopePayload(['product_category_ids' => [$category->id], 'product_ids' => [$product->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('product_ids');
    $this->postJson('/api/commission-configurations', multiScopePayload(['application_scope' => 'PRODUCT', 'product_ids' => [$product->id], 'product_category_ids' => [$category->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('product_category_ids');
    $this->postJson('/api/commission-configurations', multiScopePayload(['product_category_ids' => range(1, 501)]))
        ->assertUnprocessable()->assertJsonValidationErrors('product_category_ids');
    $this->postJson('/api/commission-configurations', multiScopePayload(['product_category_ids' => [$inactive->id]]))
        ->assertCreated();

    $inactiveCategoryProduct = Product::factory()->create(['category_id' => $inactive->id]);
    $productPayload = multiScopePayload(['application_scope' => 'PRODUCT', 'product_ids' => [$inactiveCategoryProduct->id]]);
    $this->postJson('/api/commission-configurations', $productPayload)
        ->assertUnprocessable()->assertJsonValidationErrors('product_ids.0');

    $saved = CommissionConfiguration::factory()->forProducts([$product->id])->create();
    $inactiveCategoryProduct->update(['is_active' => false]);
    $product->update(['is_active' => false]);
    $this->patchJson("/api/commission-configurations/{$saved->id}", ['product_ids' => [$product->id]])->assertOk();
    $this->patchJson("/api/commission-configurations/{$saved->id}", ['product_ids' => [$product->id, $inactiveCategoryProduct->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('product_ids.1');
});

it('replaces the lists on update and empties the list of the scope left behind', function () {
    Sanctum::actingAs(multiScopeActor());
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    $product = Product::factory()->create();
    $rule = CommissionConfiguration::factory()->forCategories([$a->id])->create();

    $this->patchJson("/api/commission-configurations/{$rule->id}", ['product_category_ids' => [$a->id, $b->id]])
        ->assertOk()->assertJsonCount(2, 'data.product_categories');

    $this->patchJson("/api/commission-configurations/{$rule->id}", ['application_scope' => 'PRODUCT'])
        ->assertUnprocessable()->assertJsonValidationErrors('product_ids');

    $this->patchJson("/api/commission-configurations/{$rule->id}", ['application_scope' => 'PRODUCT', 'product_ids' => [$product->id]])
        ->assertOk()
        ->assertJsonPath('data.product_category_ids', [])
        ->assertJsonPath('data.product_ids', [$product->id]);

    expect(DB::table('commission_configuration_product_category')->where('commission_configuration_id', $rule->id)->count())->toBe(0);
});

it('AC-006 shows several names, filters on any of them and sorts on the first', function () {
    Sanctum::actingAs(multiScopeActor());
    $alpha = ProductCategory::factory()->create(['name' => 'Alpha cat']);
    $beta = ProductCategory::factory()->create(['name' => 'Beta cat']);
    $gamma = ProductCategory::factory()->create(['name' => 'Gamma cat']);
    CommissionConfiguration::factory()->forCategories([$beta->id, $alpha->id])->create(['name' => 'Both']);
    CommissionConfiguration::factory()->forCategories([$gamma->id])->create(['name' => 'Gamma only']);
    CommissionConfiguration::factory()->forRecipient('user', User::factory()->create()->id)->create(['name' => 'No category']);

    $rows = fn (array $body): array => $this->postJson('/api/tables/commission-configurations/rows', ['startRow' => 0, 'endRow' => 25, ...$body])
        ->assertOk()->json('items');

    $all = collect($rows(['sortModel' => [['colId' => 'category', 'sort' => 'asc']]]));
    expect($all->firstWhere('name', 'Both')['category'])->toBe('Alpha cat, Beta cat')
        ->and($all->pluck('name')->all())->toContain('Both', 'Gamma only');

    $byBeta = $rows(['filterModel' => ['category' => ['filterType' => 'set', 'values' => ['Beta cat']]]]);
    expect(collect($byBeta)->pluck('name')->all())->toBe(['Both']);

    $blank = $rows(['filterModel' => ['category' => ['filterType' => 'set', 'values' => [null]]]]);
    expect(collect($blank)->pluck('name')->all())->not->toContain('Both', 'Gamma only');

    $this->postJson('/api/tables/commission-configurations/values', ['columnId' => 'category'])
        ->assertOk()->assertJsonPath('data.values', [null, 'Alpha cat', 'Beta cat', 'Gamma cat']);
});

it('AC-007 blocks deleting a used category and drops the pivot rows with the rule', function () {
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create();
    $rule = CommissionConfiguration::factory()->forCategories([$category->id])->create();
    $productRule = CommissionConfiguration::factory()->forProducts([$product->id])->create();

    expect(fn () => DB::table('product_categories')->where('id', $category->id)->delete())->toThrow(QueryException::class);

    $rule->delete();
    $productRule->delete();

    expect(DB::table('commission_configuration_product_category')->count())->toBe(0)
        ->and(DB::table('commission_configuration_product')->count())->toBe(0);
});

it('AC-007 lists the commission rules among the uses of a category', function () {
    $category = ProductCategory::factory()->create();
    CommissionConfiguration::factory()->forCategories([$category->id])->create();

    expect(DB::table('commission_configuration_product_category')->where('product_category_id', $category->id)->exists())->toBeTrue()
        ->and(UsageMap::USAGES['product_categories'])
        ->toHaveKey('commission_configuration_product_category.product_category_id');
});

it('AC-008 logs a list change with the old and new ids, and nothing when it is unchanged', function () {
    Sanctum::actingAs(multiScopeActor());
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    $rule = CommissionConfiguration::factory()->forCategories([$a->id])->create();

    $this->patchJson("/api/commission-configurations/{$rule->id}", ['product_category_ids' => [$a->id]])->assertOk();
    expect(Activity::query()->where('subject_id', $rule->id)->where('description', 'Commission scope lists change')->count())->toBe(0);

    $this->patchJson("/api/commission-configurations/{$rule->id}", ['product_category_ids' => [$b->id, $a->id]])->assertOk();
    $entry = Activity::query()->where('subject_id', $rule->id)->where('description', 'Commission scope lists change')->sole();

    expect($entry->properties['old'])->toBe(['product_category_ids' => [$a->id]])
        ->and($entry->properties['attributes'])->toBe(['product_category_ids' => collect([$a->id, $b->id])->sort()->values()->all()]);
});

it('keeps the applied commissions pointing at the rule', function () {
    $rule = CommissionConfiguration::factory()->forProducts([Product::factory()->create()->id])->create();
    QuoteLineCommission::factory()->create(['commission_configuration_id' => $rule->id]);

    Sanctum::actingAs(multiScopeActor());
    $this->deleteJson("/api/commission-configurations/{$rule->id}")->assertConflict();
    expect(DB::table('commission_configuration_product')->where('commission_configuration_id', $rule->id)->count())->toBe(1);
});

it('AC-005 copies every existing rule value into the pivots and restores them on down()', function () {
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create();
    $migration = require database_path('migrations/2026_12_26_100000_create_commission_configuration_product_pivots.php');

    $categoryRule = CommissionConfiguration::factory()->forCategories([$category->id])->create();
    $productRule = CommissionConfiguration::factory()->forProducts([$product->id])->create();
    $recipientRule = CommissionConfiguration::factory()->forRecipient('user', User::factory()->create()->id)->create();
    $extra = ProductCategory::factory()->create();
    $categoryRule->productCategories()->attach($extra->id);

    $migration->down();

    expect(Schema::hasColumns('commission_configurations', ['product_category_id', 'product_id']))->toBeTrue()
        ->and(Schema::hasTable('commission_configuration_product'))->toBeFalse()
        ->and(DB::table('commission_configurations')->where('id', $categoryRule->id)->value('product_category_id'))->toBe(min($category->id, $extra->id))
        ->and((int) DB::table('commission_configurations')->where('id', $productRule->id)->value('product_id'))->toBe($product->id)
        ->and(DB::table('commission_configurations')->where('id', $recipientRule->id)->value('product_id'))->toBeNull();

    $migration->up();

    expect(Schema::hasColumn('commission_configurations', 'product_id'))->toBeFalse()
        ->and(DB::table('commission_configuration_product_category')->where('commission_configuration_id', $categoryRule->id)->pluck('product_category_id')->all())->toBe([min($category->id, $extra->id)])
        ->and(DB::table('commission_configuration_product')->where('commission_configuration_id', $productRule->id)->pluck('product_id')->all())->toBe([$product->id])
        ->and(DB::table('commission_configuration_product')->where('commission_configuration_id', $recipientRule->id)->count())->toBe(0);
});
