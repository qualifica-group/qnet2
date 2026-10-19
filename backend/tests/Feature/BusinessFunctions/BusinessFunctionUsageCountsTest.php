<?php

use App\Models\BusinessFunction;
use App\Models\CampaignProductLine;
use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\ProjectProductLine;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0281: one count per kind of association on the business functions grid.
uses(RefreshDatabase::class);

if (! function_exists('usageCountsViewer')) {
    function usageCountsViewer(): User
    {
        Permission::findOrCreate('business-functions.viewAny');
        $user = User::factory()->create();
        $user->givePermissionTo('business-functions.viewAny');

        return $user;
    }
}

it('AC-005: counts every kind of association, distinct on the product lines', function () {
    $function = BusinessFunction::factory()->create(['name' => 'Formazione']);
    BusinessFunction::factory()->create(['name' => 'Empty']);

    $function->users()->sync(User::factory()->count(2)->create()->pluck('id'));
    ProductCategory::factory()->count(3)->create(['business_function_id' => $function->id]);
    BusinessFunction::factory()->create(['parent_id' => $function->id]);

    // Two lines of one owner count once.
    $profile = EmploymentProfile::factory()->create();
    EmploymentProductLine::factory()->count(2)->create(['employment_profile_id' => $profile->id, 'business_function_id' => $function->id]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->count(2)->create(['opportunity_id' => $opportunity->id, 'business_function_id' => $function->id]);
    OpportunityProductLine::factory()->create(['business_function_id' => $function->id]);
    ProjectProductLine::factory()->create(['business_function_id' => $function->id]);
    CampaignProductLine::factory()->count(2)->create(['business_function_id' => $function->id]);
    PurchaseRequest::factory()->create(['business_function_id' => $function->id]);

    Sanctum::actingAs(usageCountsViewer());

    $rows = collect($this->postJson('/api/tables/business-functions/rows', ['startRow' => 0, 'endRow' => 100])
        ->assertOk()
        ->json('items'))->keyBy('name');

    expect($rows['Formazione'])->toMatchArray([
        'users_count' => 2,
        'product_categories_count' => 3,
        'competences_count' => 1,
        'opportunities_count' => 2,
        'projects_count' => 1,
        'campaigns_count' => 2,
        'purchase_requests_count' => 1,
        'children_count' => 1,
    ])->and($rows['Empty'])->toMatchArray([
        'users_count' => 0,
        'product_categories_count' => 0,
        'competences_count' => 0,
        'opportunities_count' => 0,
        'children_count' => 0,
    ]);
});

it('AC-006: sorts the rows by a count', function () {
    $few = BusinessFunction::factory()->create(['name' => 'Few']);
    $many = BusinessFunction::factory()->create(['name' => 'Many']);
    $few->users()->sync(User::factory()->create()->id);
    $many->users()->sync(User::factory()->count(3)->create()->pluck('id'));

    Sanctum::actingAs(usageCountsViewer());

    $names = $this->postJson('/api/tables/business-functions/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'users_count', 'sort' => 'desc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('Many', $names, true))->toBeLessThan(array_search('Few', $names, true));
});
