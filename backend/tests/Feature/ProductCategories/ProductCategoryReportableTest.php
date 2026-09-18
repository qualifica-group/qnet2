<?php

use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductCategories\ReportableInheritance;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `is_reportable` (spec 0131): whether a category is a row of the Gestione
 * Richieste / Iscritti report. User directive 2026-09-18 (supersedes "never
 * inherited"): the column is the node's own override, null = inherit the
 * parent's effective value; a child of a reportable category is reportable by
 * default and can be forced off.
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

it('create: omitting is_reportable yields an inheriting root outside the report', function () {
    Sanctum::actingAs(reportableCategoryActorWith(['product-categories.create']));

    $this->postJson('/api/product-categories', ['name' => 'Default'])
        ->assertCreated()
        ->assertJsonPath('data.is_reportable', null)
        ->assertJsonPath('data.effective_is_reportable', false)
        ->assertJsonPath('data.is_reportable_source_category', null);
});

it('a child inherits a reportable parent by default and can be forced off, then back to inherit', function () {
    Sanctum::actingAs(reportableCategoryActorWith(['product-categories.create', 'product-categories.update', 'product-categories.view']));

    $parent = $this->postJson('/api/product-categories', ['name' => 'Parent', 'is_reportable' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_reportable', true)
        ->assertJsonPath('data.effective_is_reportable', true)
        ->json('data.id');

    $child = $this->postJson('/api/product-categories', ['name' => 'Child', 'parent_id' => $parent])
        ->assertCreated()
        ->assertJsonPath('data.is_reportable', null)
        ->assertJsonPath('data.effective_is_reportable', true)
        ->assertJsonPath('data.is_reportable_source_category', ['id' => $parent, 'name' => 'Parent'])
        ->json('data.id');

    $grandchild = ProductCategory::factory()->create(['name' => 'Grandchild', 'parent_id' => $child]);

    $this->patchJson("/api/product-categories/{$child}", ['is_reportable' => false])
        ->assertOk()
        ->assertJsonPath('data.is_reportable', false)
        ->assertJsonPath('data.effective_is_reportable', false)
        ->assertJsonPath('data.is_reportable_source_category', null);

    // The forced-off child passes "off" to its own inheriting subtree.
    $this->getJson("/api/product-categories/{$grandchild->id}")
        ->assertOk()
        ->assertJsonPath('data.effective_is_reportable', false)
        ->assertJsonPath('data.is_reportable_source_category', ['id' => $child, 'name' => 'Child']);

    $this->patchJson("/api/product-categories/{$child}", ['is_reportable' => null])
        ->assertOk()
        ->assertJsonPath('data.is_reportable', null)
        ->assertJsonPath('data.effective_is_reportable', true);

    expect(ProductCategory::query()->find($parent)->is_reportable)->toBeTrue()
        ->and(ProductCategory::query()->find($child)->is_reportable)->toBeNull();
});

it('the categories table shows and filters the effective flag', function () {
    Sanctum::actingAs(reportableCategoryActorWith(['product-categories.viewAny']));

    $root = ProductCategory::factory()->reportable()->create(['name' => 'Root']);
    $inheriting = ProductCategory::factory()->childOf($root)->create(['name' => 'Inheriting']);
    $forcedOff = ProductCategory::factory()->childOf($root)->create(['name' => 'Forced off', 'is_reportable' => false]);
    $outside = ProductCategory::factory()->create(['name' => 'Outside']);

    $rows = collect($this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->pluck('is_reportable', 'id');

    expect($rows->all())->toEqualCanonicalizing([
        $root->id => true, $inheriting->id => true, $forcedOff->id => false, $outside->id => false,
    ]);

    $filtered = collect($this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['is_reportable' => ['filterType' => 'boolean', 'values' => [true]]],
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect($filtered)->toEqualCanonicalizing([$root->id, $inheriting->id]);
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

    expect($category->fresh()->is_reportable)->toBeNull();
});

it('seeds the production report categories and lets their subcategories inherit the flag (directive 2026-09-18)', function () {
    test()->seed(QualificaCatalogSeeder::class);

    $forced = ProductCategory::query()->where('is_reportable', true)->pluck('name')->sort()->values()->all();

    expect($forced)->toBe(['Autofinanziato', 'Autoimpiego', 'DIL', 'GOL', 'Orientamento Specialistico', 'Yisu'])
        ->and(ProductCategory::query()->where('is_reportable', false)->exists())->toBeFalse();

    $effective = app(ReportableInheritance::class)->effectiveMapForAll();
    $effectiveOf = static fn (string $name): bool => $effective[ProductCategory::query()->where('name', $name)->value('id')];

    expect($effectiveOf('GOL - Campania'))->toBeTrue()
        ->and($effectiveOf('DIL - Lombardia'))->toBeTrue()
        ->and($effectiveOf('Consulenza'))->toBeFalse()
        ->and($effectiveOf('APL'))->toBeFalse()
        ->and($effectiveOf('Formazione'))->toBeFalse();
});
