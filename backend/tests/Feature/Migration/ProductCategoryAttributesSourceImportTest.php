<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\Attribute;
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
if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('seedMigrationsConfig')) {
    function seedMigrationsConfig(): void
    {
        config([
            'migrations.base_url' => fakeMigrationsBaseUrl(),
            'migrations.token' => null,
            'migrations.timeout' => 5,
            'migrations.retry_times' => 1,
            'migrations.retry_sleep_ms' => 1,
            'migrations.import_batch_size' => 100,
        ]);
    }
}

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

if (! function_exists('fakeProductCategoryAttributes')) {
    /**
     * The association source re-reads the external `product-categories`
     * endpoint, taking its `attributes` link array.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    function fakeProductCategoryAttributes(array $items): void
    {
        Http::fake([
            fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
                'items' => $items,
                'pagination' => ['total' => count($items)],
            ]),
        ]);
    }
}

if (! function_exists('runCategoryAttributesFor')) {
    function runCategoryAttributesFor(User $actor): MigrationRun
    {
        $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'product-category-attributes']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}

if (! function_exists('categoryAttributeLinks')) {
    /**
     * @return array<int, object>
     */
    function categoryAttributeLinks(ProductCategory $category): array
    {
        return DB::table('attribute_category')
            ->where('category_id', $category->id)
            ->orderBy('attribute_id')
            ->orderBy('context')
            ->get()
            ->all();
    }
}

// ---------------------------------------------------------------------------
// ProductCategoryAttributesSource: link catalogue attributes onto an
// already-migrated category, by external id or by code, per declared context.
// ---------------------------------------------------------------------------

it('links attributes resolved by external id and by code, honouring the declared context', function () {
    seedMigrationsConfig();
    $byId = Attribute::factory()->create(['old_id' => 7, 'code' => 'material']);
    $byCode = Attribute::factory()->create(['old_id' => null, 'code' => 'size']);
    $category = ProductCategory::factory()->create(['old_id' => 1]);

    fakeProductCategoryAttributes([
        ['id' => 1, 'name' => 'Electronics', 'attributes' => [
            ['attribute_id' => 7, 'context' => 'product', 'is_required' => true, 'sort_order' => 3],
            ['attribute_code' => 'size', 'context' => 'quote'],
        ]],
    ]);

    $fresh = runCategoryAttributesFor(migrationsSuperAdminActor());

    $links = categoryAttributeLinks($category);

    expect($links)->toHaveCount(2)
        ->and($links[0]->attribute_id)->toBe($byId->id)
        ->and($links[0]->context)->toBe('product')
        ->and((bool) $links[0]->is_required)->toBeTrue()
        ->and($links[0]->sort_order)->toBe(3)
        ->and($links[1]->attribute_id)->toBe($byCode->id)
        ->and($links[1]->context)->toBe('quote')
        ->and((bool) $links[1]->is_required)->toBeFalse();

    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->skipped_rows)->toBe(0);
});

it('links the same attribute to both contexts as two separate pivot rows', function () {
    seedMigrationsConfig();
    $attribute = Attribute::factory()->create(['old_id' => 7]);
    $category = ProductCategory::factory()->create(['old_id' => 1]);

    fakeProductCategoryAttributes([
        ['id' => 1, 'attributes' => [
            ['attribute_id' => 7, 'context' => 'product'],
            ['attribute_id' => 7, 'context' => 'quote'],
        ]],
    ]);

    runCategoryAttributesFor(migrationsSuperAdminActor());

    $links = categoryAttributeLinks($category);

    expect($links)->toHaveCount(2)
        ->and(array_map(fn (object $link): string => $link->context, $links))->toBe(['product', 'quote'])
        ->and(array_unique(array_map(fn (object $link): int => $link->attribute_id, $links)))->toBe([$attribute->id]);
});

it('applies the resolved links and warns on each unresolved attribute reference', function () {
    seedMigrationsConfig();
    $attribute = Attribute::factory()->create(['old_id' => 7]);
    $category = ProductCategory::factory()->create(['old_id' => 1]);

    fakeProductCategoryAttributes([
        ['id' => 1, 'attributes' => [
            ['attribute_id' => 7, 'context' => 'product'],
            ['attribute_id' => 999, 'context' => 'product'],
            ['attribute_code' => 'ghost', 'context' => 'product'],
        ]],
    ]);

    $fresh = runCategoryAttributesFor(migrationsSuperAdminActor());

    $links = categoryAttributeLinks($category);
    $messages = collect($fresh->report)->pluck('message')->implode(' | ');

    expect($links)->toHaveCount(1)
        ->and($links[0]->attribute_id)->toBe($attribute->id)
        ->and($fresh->created_rows)->toBe(1)
        ->and($messages)->toContain('999')
        ->and($messages)->toContain('ghost');
});

it('ignores a link whose context is missing or unknown, warning on each', function () {
    seedMigrationsConfig();
    Attribute::factory()->create(['old_id' => 7]);
    $category = ProductCategory::factory()->create(['old_id' => 1]);

    fakeProductCategoryAttributes([
        ['id' => 1, 'attributes' => [
            ['attribute_id' => 7],
            ['attribute_id' => 7, 'context' => 'lead'],
        ]],
    ]);

    $fresh = runCategoryAttributesFor(migrationsSuperAdminActor());

    $messages = collect($fresh->report)->pluck('message')->implode(' | ');

    expect(categoryAttributeLinks($category))->toHaveCount(0)
        ->and($fresh->created_rows)->toBe(0)
        ->and($fresh->skipped_rows)->toBe(1)
        ->and($messages)->toContain('without context')
        ->and($messages)->toContain('lead');
});

it('re-importing the same links is idempotent (skip, no duplicate, extras untouched)', function () {
    seedMigrationsConfig();
    Attribute::factory()->create(['old_id' => 7]);
    $category = ProductCategory::factory()->create(['old_id' => 1]);

    fakeProductCategoryAttributes([
        ['id' => 1, 'attributes' => [
            ['attribute_id' => 7, 'context' => 'product', 'is_required' => true, 'sort_order' => 2],
        ]],
    ]);

    $actor = migrationsSuperAdminActor();
    runCategoryAttributesFor($actor);
    $second = runCategoryAttributesFor($actor);

    $links = categoryAttributeLinks($category);

    expect($links)->toHaveCount(1)
        ->and((bool) $links[0]->is_required)->toBeTrue()
        ->and($links[0]->sort_order)->toBe(2)
        ->and($second->created_rows)->toBe(0)
        ->and($second->skipped_rows)->toBe(1);
});

it('never detaches an assignment the external system did not send', function () {
    seedMigrationsConfig();
    $migrated = Attribute::factory()->create(['old_id' => 7]);
    $native = Attribute::factory()->create(['old_id' => null]);
    $category = ProductCategory::factory()->create(['old_id' => 1]);

    DB::table('attribute_category')->insert([
        'attribute_id' => $native->id,
        'category_id' => $category->id,
        'context' => 'product',
        'is_required' => false,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    fakeProductCategoryAttributes([
        ['id' => 1, 'attributes' => [['attribute_id' => 7, 'context' => 'product']]],
    ]);

    runCategoryAttributesFor(migrationsSuperAdminActor());

    expect(array_map(fn (object $link): int => $link->attribute_id, categoryAttributeLinks($category)))
        ->toContain($native->id)
        ->toContain($migrated->id);
});

it('skips with a warning when the product category itself is not migrated', function () {
    seedMigrationsConfig();
    Attribute::factory()->create(['old_id' => 7]);

    fakeProductCategoryAttributes([
        ['id' => 77, 'attributes' => [['attribute_id' => 7, 'context' => 'product']]],
    ]);

    $fresh = runCategoryAttributesFor(migrationsSuperAdminActor());

    expect(DB::table('attribute_category')->count())->toBe(0)
        ->and($fresh->created_rows)->toBe(0)
        ->and($fresh->skipped_rows)->toBe(1)
        ->and($fresh->report[0]['level'])->toBe('warning')
        ->and($fresh->report[0]['message'])->toContain('77');
});
