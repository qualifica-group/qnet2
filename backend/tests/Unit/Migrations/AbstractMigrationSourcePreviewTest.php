<?php

use App\Migrations\MigrationQuery;
use App\Migrations\Sources\RolesSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-007 — AbstractMigrationSource::preview: mapping + pagination + total
//
// The external CRM speaks OUR API dialect: request params `offset`/`limit`
// (translated from the internal page/per_page), response envelope
// `{items:[...], pagination:{total,offset,limit,total_pages}}`.
// ---------------------------------------------------------------------------

it('translates page/per_page to offset/limit and maps records to rows keyed by column id', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [
                ['id' => 10, 'name' => 'operator', 'description' => 'Front desk'],
                ['id' => 11, 'name' => 'reviewer'],
            ],
            'pagination' => ['total' => 5, 'offset' => 2, 'limit' => 2, 'total_pages' => 3],
        ]),
    ]);

    $page = app(RolesSource::class)->preview(new MigrationQuery(page: 2, perPage: 2));

    // Every declared native column is keyed on every row: a column the external
    // record omits is null-filled, never absent, or the preview grid would
    // shift its cells.
    expect($page->rows)->toBe([
        ['id' => 10, 'name' => 'operator', 'description' => 'Front desk'],
        ['id' => 11, 'name' => 'reviewer', 'description' => null],
    ])
        ->and($page->page)->toBe(2)
        ->and($page->perPage)->toBe(2)
        ->and($page->total)->toBe(5)
        ->and($page->hasMore)->toBeTrue(); // offset(2)+limit(2)=4 < 5

    Http::assertSent(fn ($request) => $request['offset'] === 2 && $request['limit'] === 2);
});

it('has_more is false once the last page is reached (known total)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [['id' => 10, 'name' => 'operator']],
            'pagination' => ['total' => 1, 'offset' => 0, 'limit' => 50, 'total_pages' => 1],
        ]),
    ]);

    $page = app(RolesSource::class)->preview(new MigrationQuery(page: 1, perPage: 50));

    expect($page->total)->toBe(1)
        ->and($page->hasMore)->toBeFalse();

    Http::assertSent(fn ($request) => $request['offset'] === 0 && $request['limit'] === 50);
});

it('total is null when pagination.total is absent (AC-007)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [['id' => 10, 'name' => 'operator']],
        ]),
    ]);

    $page = app(RolesSource::class)->preview(new MigrationQuery(page: 1, perPage: 50));

    expect($page->total)->toBeNull()
        // A single record on a 50-per-page request is clearly the last page.
        ->and($page->hasMore)->toBeFalse();
});
