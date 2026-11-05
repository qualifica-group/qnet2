<?php

use App\Models\ChartAccount;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RevenueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('AC-006: product and category accept and return revenue_type_id with the effective value', function () {
    $type = revenueSubAccount();
    $root = ProductCategory::factory()->create(['name' => 'Root cat']);
    $child = ProductCategory::factory()->create(['parent_id' => $root->id]);
    $product = Product::factory()->create(['category_id' => $child->id]);
    [$categoryEditor, $productEditor] = [productCategoryUserWith(['view', 'update']), productUserWith(['view', 'update'])];
    Sanctum::actingAs($categoryEditor);

    $this->patchJson("/api/product-categories/{$root->id}", ['revenue_type_id' => $type->id])
        ->assertOk()
        ->assertJsonPath('data.revenue_type_id', $type->id)
        ->assertJsonPath('data.revenue_type.code', $type->code)
        ->assertJsonPath('data.effective_revenue_type.inherited', false)
        ->assertJsonPath('data.effective_revenue_type.source_category', null);

    $this->getJson("/api/product-categories/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.revenue_type_id', null)
        ->assertJsonPath('data.effective_revenue_type.inherited', true)
        ->assertJsonPath('data.effective_revenue_type.source_category', ['id' => $root->id, 'name' => 'Root cat'])
        ->assertJsonPath('data.effective_revenue_type.revenue_type.id', $type->id);

    Sanctum::actingAs($productEditor);
    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.revenue_type', null)
        ->assertJsonPath('data.effective_revenue_type.inherited', true)
        ->assertJsonPath('data.effective_revenue_type.source_category.id', $root->id);

    $own = revenueSubAccount();
    $this->patchJson("/api/products/{$product->id}", ['revenue_type_id' => $own->id])
        ->assertOk()
        ->assertJsonPath('data.revenue_type_id', $own->id)
        ->assertJsonPath('data.effective_revenue_type.inherited', false);
});

it('AC-006: an inactive or unknown sub-account is a 422 on product and category', function () {
    $inactive = revenueSubAccount(['is_active' => false]);
    $product = Product::factory()->create();
    $category = ProductCategory::factory()->create();

    [$productEditor, $categoryEditor] = [productUserWith(['view', 'update']), productCategoryUserWith(['view', 'update'])];

    Sanctum::actingAs($productEditor);
    foreach ([$inactive->id, 999999] as $id) {
        $this->patchJson("/api/products/{$product->id}", ['revenue_type_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('revenue_type_id');
    }

    Sanctum::actingAs($categoryEditor);
    foreach ([$inactive->id, 999999] as $id) {
        $this->patchJson("/api/product-categories/{$category->id}", ['revenue_type_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('revenue_type_id');
    }
});

it('AC-011: for-select lists only selectable sub-accounts, grouped, searchable and hydrated by ids', function () {
    resetChartOfAccounts();
    [$group, $account] = revenueGroupAccountPair();
    $group->update(['code' => '70', 'name' => 'Ricavi']);
    $account->update(['code' => '70.01', 'name' => 'Servizi']);
    $second = RevenueType::factory()->create(['chart_account_id' => $account->id, 'code' => '70.01.10', 'name' => 'Corsi']);
    $first = RevenueType::factory()->create(['chart_account_id' => $account->id, 'code' => '70.01.2', 'name' => 'Consulenza']);
    $inactive = RevenueType::factory()->inactive()->create(['chart_account_id' => $account->id, 'code' => '70.01.3']);
    [, $deadAccount] = revenueGroupAccountPair();
    $deadAccount->update(['is_active' => false]);
    RevenueType::factory()->create(['chart_account_id' => $deadAccount->id]);
    Sanctum::actingAs(chartUserWith(['revenue-types.view']));

    $this->getJson('/api/revenue-types/for-select')
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.offset', 0)
        ->assertJsonPath('data.items.0.id', $first->id)
        ->assertJsonPath('data.items.1.id', $second->id)
        ->assertJsonPath('data.items.0.path', '70 Ricavi > 70.01 Servizi')
        ->assertJsonPath('data.items.0.group', ['code' => '70', 'name' => 'Ricavi'])
        ->assertJsonPath('data.items.0.account', ['code' => '70.01', 'name' => 'Servizi']);

    $this->getJson('/api/revenue-types/for-select?search=corsi')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $second->id);
    $this->getJson('/api/revenue-types/for-select?search=70.01.2')->assertOk()->assertJsonPath('data.items.0.id', $first->id);
    $this->getJson("/api/revenue-types/for-select?ids[]={$inactive->id}")->assertOk()
        ->assertJsonPath('data.total', 2)->assertJsonCount(3, 'data.items')->assertJsonPath('data.items.2.is_active', false);
});

it('AC-011: for-select is 403 without any of revenue-types.view, invoices.view, products.view', function () {
    [$chartOnly, $productViewer] = [chartUserWith(['chart-accounts.view']), productUserWith(['view'])];
    Permission::findOrCreate('invoices.view');

    Sanctum::actingAs($chartOnly);
    $this->getJson('/api/revenue-types/for-select')->assertForbidden();

    Sanctum::actingAs($productViewer);
    $this->getJson('/api/revenue-types/for-select')->assertOk();
});

it('AC-012: setting the institution flag clears it from the others; the tree and resource expose it', function () {
    resetChartOfAccounts();
    $a = institutionCommissionDefault();
    $b = revenueSubAccount();
    Sanctum::actingAs(chartUserWith(['revenue-types.update', 'chart-accounts.view', 'chart-accounts.viewAny']));

    $this->patchJson("/api/revenue-types/{$b->id}", ['is_institution_commission_default' => true])
        ->assertOk()->assertJsonPath('data.is_institution_commission_default', true);

    expect($a->fresh()->is_institution_commission_default)->toBeFalse()
        ->and(RevenueType::query()->where('is_institution_commission_default', true)->count())->toBe(1);

    $this->patchJson("/api/revenue-types/{$b->id}", ['is_institution_commission_default' => 'x'])->assertUnprocessable();
    $this->patchJson("/api/revenue-types/{$b->id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.is_institution_commission_default', true);
});

it('AC-012: the flag is 403 without revenue-types.update and the tree shows it on the sub-account', function () {
    resetChartOfAccounts();
    $flagged = institutionCommissionDefault();
    Sanctum::actingAs(chartUserWith(['revenue-types.view']));
    $this->patchJson("/api/revenue-types/{$flagged->id}", ['is_institution_commission_default' => false])->assertForbidden();

    Sanctum::actingAs(chartUserWith(['chart-accounts.view', 'chart-accounts.viewAny']));
    $tree = $this->getJson('/api/chart-of-accounts/tree')->assertOk()->json('data.groups');
    $sub = collect($tree)->pluck('accounts')->flatten(1)->pluck('sub_accounts')->flatten(1)->firstWhere('id', $flagged->id);

    expect($sub['is_institution_commission_default'])->toBeTrue();
});

it('a revenue sub-account in use cannot be deleted (409) and an unused one can', function () {
    $used = revenueSubAccount();
    Product::factory()->create(['revenue_type_id' => $used->id]);
    $free = revenueSubAccount();
    Sanctum::actingAs(chartUserWith(['revenue-types.delete']));

    $this->deleteJson("/api/revenue-types/{$used->id}")->assertStatus(409);
    $this->deleteJson("/api/revenue-types/{$free->id}")->assertOk();
    expect(ChartAccount::query()->count())->toBeGreaterThan(0);
});
