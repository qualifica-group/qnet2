<?php

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0080, data_contract (B): the work panel's manager_labels field, so the
// "Operatore (GA2)" field/column can be rietichettata with the request's
// category-resolved level-2 label. operator_id/operator stay untouched.

uses(RefreshDatabase::class);

if (! function_exists('managerLabelRequestManagementUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function managerLabelRequestManagementUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

it('the work panel exposes manager_labels resolved from the request\'s category, operator_id untouched', function (): void {
    $actor = managerLabelRequestManagementUserWith(['view', 'viewAll']);
    $category = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore Tecnico']]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    $operator = User::factory()->create();
    $opportunity->managers()->attach($operator->id, ['position' => 2]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.manager_labels', ['2' => 'Operatore Tecnico'])
        ->assertJsonPath('data.operator_id', $operator->id)
        ->assertJsonPath('data.operator.id', $operator->id);
});

it('the work panel manager_labels is [] when the category has no configured labels', function (): void {
    $actor = managerLabelRequestManagementUserWith(['view', 'viewAll']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.manager_labels', []);
});
