<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Migrations\Sources\AttributeLayoutsSource;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\MigrationRun;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// The shared helpers (fakeMigrationsBaseUrl/seedMigrationsConfig/
// migrationsSuperAdminActor/runMigrationJobFor) are defined once, guarded by
// function_exists, across the Migration feature suite (see CompaniesSourceImportTest).

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMigrationJobFor')) {
    function runMigrationJobFor(MigrationRun $run): void
    {
        (new RunMigrationJob($run->id))->handle(app(MigrationService::class));
    }
}

/**
 * @param  array<int, string>  $codes
 * @return array<string, mixed>
 */
function layoutItem(int $categoryId, array $codes, string $context = 'work_order', string $formMode = 'all'): array
{
    return [
        'id' => "{$categoryId}-{$context}-{$formMode}",
        'category_id' => $categoryId,
        'context' => $context,
        'form_mode' => $formMode,
        'layout' => ['sections' => [[
            'id' => 'details', 'title' => 'Details', 'description' => null, 'variant' => 'default',
            'collapsible' => false, 'default_collapsed' => false, 'columns' => 2, 'sort_order' => 0,
            'rows' => [['id' => 'row-1', 'items' => array_map(
                fn (string $code): array => ['attribute_code' => $code, 'width' => 'half'],
                $codes,
            )]],
        ]]],
    ];
}

/**
 * A category with old_id 70 and two attributes linked in the work_order context.
 */
function layoutCategoryWithLinks(): ProductCategory
{
    $category = ProductCategory::factory()->create(['old_id' => 70]);

    foreach (['standard', 'scopo'] as $index => $code) {
        $attribute = Attribute::factory()->create(['code' => $code, 'type' => 'text']);
        DB::table('attribute_category')->insert([
            'attribute_id' => $attribute->id, 'category_id' => $category->id, 'context' => 'work_order',
            'is_required' => false, 'sort_order' => $index, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $category;
}

function runLayoutsWith(array $items): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/attribute-layouts*' => Http::response([
            'items' => $items,
            'pagination' => ['total' => count($items)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'attribute-layouts']);
    runMigrationJobFor($run);

    return $run->fresh();
}

it('AC-007: imports a layout for a category whose codes are linked, idempotently', function () {
    seedMigrationsConfig();
    $category = layoutCategoryWithLinks();
    $item = layoutItem(70, ['standard', 'scopo']);

    $run = runLayoutsWith([$item]);

    $layout = AttributeLayout::query()->where('product_category_id', $category->id)->sole();

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($layout->context)->toBe(AttributeContext::WorkOrder)
        ->and($layout->form_mode)->toBe(LayoutFormScope::All)
        ->and($layout->layout['sections'][0]['title'])->toBe('Details')
        ->and($layout->layout['sections'][0]['rows'][0]['items'][0]['attribute_code'])->toBe('standard');

    $snapshot = $layout->layout;
    $second = runLayoutsWith([$item]);

    expect(AttributeLayout::query()->count())->toBe(1)
        ->and($second->created_rows)->toBe(0)
        ->and($second->skipped_rows)->toBe(1)
        ->and(AttributeLayout::query()->sole()->layout)->toBe($snapshot);
});

it('AC-007: skips a layout that is already configured without overwriting it', function () {
    seedMigrationsConfig();
    $category = layoutCategoryWithLinks();
    AttributeLayout::query()->create([
        'product_category_id' => $category->id, 'context' => 'work_order', 'form_mode' => 'all',
        'layout' => ['sections' => [['id' => 'manual', 'title' => 'Manual']]],
    ]);

    $run = runLayoutsWith([layoutItem(70, ['standard'])]);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->created_rows)->toBe(0)
        ->and(AttributeLayout::query()->sole()->layout['sections'][0]['title'])->toBe('Manual');
});

it('AC-007: skips a row for an unknown category with a warning', function () {
    seedMigrationsConfig();

    $run = runLayoutsWith([layoutItem(999, ['standard'])]);

    expect($run->skipped_rows)->toBe(1)
        ->and($run->failed_rows)->toBe(0)
        ->and(AttributeLayout::query()->count())->toBe(0)
        ->and(json_encode($run->report))->toContain('Unresolved product category reference');
});

it('AC-007: fails a row whose code is not linked and writes nothing', function () {
    seedMigrationsConfig();
    layoutCategoryWithLinks();
    Attribute::factory()->create(['code' => 'unlinked', 'type' => 'text']);

    $run = runLayoutsWith([layoutItem(70, ['standard', 'unlinked'])]);

    expect($run->failed_rows)->toBe(1)
        ->and($run->created_rows)->toBe(0)
        ->and(AttributeLayout::query()->count())->toBe(0)
        ->and(json_encode($run->report))->toContain('Invalid layout');
});

it('fails a row with an unknown context or form_mode', function () {
    seedMigrationsConfig();
    layoutCategoryWithLinks();

    $run = runLayoutsWith([
        layoutItem(70, ['standard'], 'bogus'),
        layoutItem(70, ['standard'], 'work_order', 'bogus'),
    ]);

    expect($run->failed_rows)->toBe(2)
        ->and(AttributeLayout::query()->count())->toBe(0);
});

it('exposes a sample response carrying a layout blob', function () {
    seedMigrationsConfig();

    $sample = app(AttributeLayoutsSource::class)->sampleResponse();

    expect($sample['items'][0])->toHaveKeys(['id', 'category_id', 'context', 'form_mode', 'layout'])
        ->and($sample['items'][0]['layout']['sections'])->not->toBeEmpty();
});
