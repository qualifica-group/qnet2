<?php

require_once __DIR__.'/../../../Helpers/RequestImportHelpers.php';

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * A configuring run over a file whose "Categoria Prodotto" column holds $values.
 *
 * @param  array<int, string>  $values
 */
function categoryDefaultRun(array $values): ImportRun
{
    Storage::fake('local');
    Storage::disk('local')->put('imports/cat.csv', "Nome,Categoria Prodotto\n".implode("\n", array_map(fn (string $value): string => "Anna,{$value}", $values))."\n");

    return ImportRun::factory()->create([
        'resource' => 'request-management',
        'status' => ImportStatus::Configuring,
        'stored_path' => 'imports/cat.csv',
        'detected_columns' => [
            ['name' => 'Nome', 'index' => 0, 'duplicate' => false],
            ['name' => 'Categoria Prodotto', 'index' => 1, 'duplicate' => false],
        ],
    ]);
}

function categoryDefaultOf(ImportRun $run): mixed
{
    Sanctum::actingAs(requestImportActor());

    $fields = test()->getJson("/api/imports/request-management/{$run->id}")->assertOk()->json('data.import_run.global_fields');

    return collect($fields)->firstWhere('id', 'product_category_id')['default'];
}

it('D-5: one distinct category name resolves (case, accents, spacing) to the selectable active category', function () {
    $category = ProductCategory::factory()->create(['name' => 'Orientamento Specialistico', 'is_selectable' => true, 'is_active' => true]);
    ProductCategory::factory()->create(['name' => 'Altra', 'is_selectable' => true, 'is_active' => true]);

    // Strictly a JSON integer: the wizard ignores a string default ("26").
    $default = categoryDefaultOf(categoryDefaultRun(['orientamento  specialistico', 'Orientamento Specialistico', '']));

    expect($default)->toBeInt()->toBe($category->id);
});

it('D-5: two distinct names, an unknown name, or no value leave the default null', function () {
    ProductCategory::factory()->create(['name' => 'Orientamento Specialistico', 'is_selectable' => true, 'is_active' => true]);
    ProductCategory::factory()->create(['name' => 'Altra', 'is_selectable' => true, 'is_active' => true]);

    expect(categoryDefaultOf(categoryDefaultRun(['Orientamento Specialistico', 'Altra'])))->toBeNull()
        ->and(categoryDefaultOf(categoryDefaultRun(['Sconosciuta'])))->toBeNull()
        ->and(categoryDefaultOf(categoryDefaultRun([''])))->toBeNull();
});

it('D-5: a matching category that is not selectable or not active is not suggested', function () {
    ProductCategory::factory()->create(['name' => 'Contenitore', 'is_selectable' => false, 'is_active' => true]);
    ProductCategory::factory()->create(['name' => 'Spenta', 'is_selectable' => true, 'is_active' => false]);

    expect(categoryDefaultOf(categoryDefaultRun(['Contenitore'])))->toBeNull()
        ->and(categoryDefaultOf(categoryDefaultRun(['Spenta'])))->toBeNull();
});

it('D-5: leads global fields are untouched (no default added, file never read)', function () {
    $run = ImportRun::factory()->create(['resource' => 'leads', 'status' => ImportStatus::Configuring, 'stored_path' => 'imports/missing.csv',
        'detected_columns' => [['name' => 'Nome', 'index' => 0, 'duplicate' => false]]]);
    Sanctum::actingAs(leadsImportActorForCategoryDefault());

    $this->getJson("/api/imports/leads/{$run->id}")->assertOk();
});

function leadsImportActorForCategoryDefault(): User
{
    Permission::findOrCreate('leads.import');

    return User::factory()->create()->givePermissionTo('leads.import');
}
