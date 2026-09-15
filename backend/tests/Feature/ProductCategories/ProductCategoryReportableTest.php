<?php

use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `is_reportable` (spec 0131): whether a category (with its subtree) is a row
 * of the Gestione Richieste / Iscritti report. Per-node, never inherited.
 */
uses(RefreshDatabase::class);

if (! function_exists('reportableCategoryActorWith')) {
    /**
     * @param  array<int, string>  $permissions  fully qualified, e.g. `product-categories.create`
     */
    function reportableCategoryActorWith(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}

it('create: omitting is_reportable yields a category outside the report', function () {
    Sanctum::actingAs(reportableCategoryActorWith(['product-categories.create']));

    $this->postJson('/api/product-categories', ['name' => 'Default'])
        ->assertCreated()
        ->assertJsonPath('data.is_reportable', false);
});

it('create/update: is_reportable is persisted and never propagates to children', function () {
    Sanctum::actingAs(reportableCategoryActorWith(['product-categories.create', 'product-categories.update']));

    $parent = $this->postJson('/api/product-categories', ['name' => 'Parent', 'is_reportable' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_reportable', true)
        ->json('data.id');

    $this->postJson('/api/product-categories', ['name' => 'Child', 'parent_id' => $parent])
        ->assertCreated()
        ->assertJsonPath('data.is_reportable', false);

    $this->patchJson("/api/product-categories/{$parent}", ['is_reportable' => false])
        ->assertOk()
        ->assertJsonPath('data.is_reportable', false);

    expect(ProductCategory::query()->find($parent)->is_reportable)->toBeFalse();
});

it('rejects a non-boolean is_reportable', function () {
    Sanctum::actingAs(reportableCategoryActorWith(['product-categories.create']));

    $this->postJson('/api/product-categories', ['name' => 'Bad', 'is_reportable' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_reportable');
});

it('403s an update of is_reportable without product-categories.update', function () {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(reportableCategoryActorWith(['product-categories.view']));

    $this->patchJson("/api/product-categories/{$category->id}", ['is_reportable' => true])->assertForbidden();

    expect($category->fresh()->is_reportable)->toBeFalse();
});

it('seeds exactly the six historical report branches plus DIL as reportable', function () {
    test()->seed(QualificaCatalogSeeder::class);

    $reportable = ProductCategory::query()->where('is_reportable', true)->pluck('name')->sort()->values()->all();

    expect($reportable)->toBe(['APL', 'Autofinanziato', 'Autoimpiego', 'Consulenza', 'DIL', 'GOL', 'Yisu']);
});
