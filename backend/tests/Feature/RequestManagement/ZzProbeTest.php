<?php

use App\Authorization\AuthorizationRegistry;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('probe patch', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $a) {
        Permission::findOrCreate("request-management.{$a}");
    }
    $u = User::factory()->create();
    $u->givePermissionTo(['request-management.view', 'request-management.update', 'request-management.create', 'request-management.viewAll']);

    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$u->id => ['position' => 2]]);
    OpportunityProductLine::factory()->for($opportunity)->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $quote = Quote::factory()->for($opportunity)->create(['operator_id' => $u->id]);
    $product = Product::factory()->create(['category_id' => $category->id]);

    $auth = app(AuthorizationRegistry::class)->resolve('request-management');
    $perms = $auth->fieldPermissions($u->fresh(), $quote);
    dump('offer_lines perm: '.json_encode(['v' => $perms['offer_lines']->visible, 'e' => $perms['offer_lines']->editable]));
    dump('next_callback_at perm: '.json_encode(['e' => $perms['next_callback_at']->editable]));

    Sanctum::actingAs($u);
    $response = $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 150]],
    ]);
    dump('status: '.$response->status(), $response->json());

    expect(true)->toBeTrue();
});
