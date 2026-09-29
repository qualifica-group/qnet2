<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\DocumentBundle;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| `document-bundles` migration source (spec 0175, D-13, AC-018)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

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

if (! function_exists('legacyDocumentBundleFile')) {
    /**
     * One file entry in the `/document-bundles` legacy contract (spec 0175,
     * D-13): base64 content inline, `missing: false` unless overridden.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function legacyDocumentBundleFile(int $id, string $name, string $bytes, array $overrides = []): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'title' => 'Descrizione',
            'mime_type' => 'application/pdf',
            'size' => strlen($bytes),
            'content_base64' => base64_encode($bytes),
            'missing' => false,
            ...$overrides,
        ];
    }
}

if (! function_exists('runDocumentBundlesImport')) {
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    function runDocumentBundlesImport(array $items): MigrationRun
    {
        seedMigrationsConfig();

        Http::fake([
            fakeMigrationsBaseUrl().'/document-bundles*' => Http::response([
                'items' => $items,
                'pagination' => ['total' => count($items)],
            ]),
        ]);

        $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'document-bundles']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}

beforeEach(function () {
    Storage::fake('local');
});

// ---------------------------------------------------------------------------
// AC-018 — happy path: bundle + 2 files
// ---------------------------------------------------------------------------

it('AC-018: creates a DocumentBundle with its files as `documents`-collection attachments', function () {
    $run = runDocumentBundlesImport([
        [
            'id' => 7,
            'title' => 'Contratto standard',
            'files' => [
                legacyDocumentBundleFile(1, 'contratto.pdf', 'PDF-CONTENT-1'),
                legacyDocumentBundleFile(2, 'allegato.pdf', 'PDF-CONTENT-2'),
            ],
        ],
    ]);

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull();

    $bundle = DocumentBundle::query()->where('old_id', 7)->first();
    expect($bundle)->not->toBeNull()
        ->and($bundle->name)->toBe('Contratto standard')
        ->and($bundle->is_active)->toBeTrue()
        ->and($bundle->attachments()->count())->toBe(2)
        ->and($bundle->attachments()->where('collection', 'documents')->count())->toBe(2);

    foreach ($bundle->attachments as $attachment) {
        Storage::disk($attachment->disk)->assertExists($attachment->path);
    }
});

it('AC-018: re-importing the same bundle is idempotent (skip, no duplicate bundle nor file)', function () {
    $items = [[
        'id' => 8,
        'title' => 'Modello ricorrente',
        'files' => [legacyDocumentBundleFile(3, 'doc.pdf', 'BYTES')],
    ]];

    runDocumentBundlesImport($items);
    $second = runDocumentBundlesImport($items);

    expect(DocumentBundle::query()->where('old_id', 8)->count())->toBe(1)
        ->and(DocumentBundle::query()->where('old_id', 8)->first()->attachments()->count())->toBe(1)
        ->and($second->skipped_rows)->toBe(1)
        ->and($second->created_rows)->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-018 — a missing file is skipped with a warning, the bundle still imports
// ---------------------------------------------------------------------------

it('AC-018: a file reported missing by the legacy endpoint is skipped with a warning', function () {
    $run = runDocumentBundlesImport([[
        'id' => 9,
        'title' => 'Con file mancante',
        'files' => [
            legacyDocumentBundleFile(4, 'presente.pdf', 'BYTES'),
            ['id' => 5, 'name' => 'assente.pdf', 'title' => null, 'mime_type' => null, 'size' => null, 'content_base64' => null, 'missing' => true],
        ],
    ]]);

    $bundle = DocumentBundle::query()->where('old_id', 9)->first();
    expect($bundle->attachments()->count())->toBe(1);

    expect(collect($run->report)->firstWhere('level', 'warning')['message'])->toContain('missing from legacy storage');
});

// ---------------------------------------------------------------------------
// AC-018 — a file over max_size or with a disallowed MIME is skipped, not fatal
// ---------------------------------------------------------------------------

it('AC-018: a file exceeding attachments.max_size is skipped with a warning', function () {
    config(['attachments.max_size' => 1]); // 1 KB

    $run = runDocumentBundlesImport([[
        'id' => 10,
        'title' => 'Con file grande',
        'files' => [legacyDocumentBundleFile(6, 'grande.pdf', str_repeat('A', 2048))],
    ]]);

    $bundle = DocumentBundle::query()->where('old_id', 10)->first();
    expect($bundle)->not->toBeNull()
        ->and($bundle->attachments()->count())->toBe(0);

    expect(collect($run->report)->firstWhere('level', 'warning')['message'])->toContain('exceeds the');
});

it('AC-018: a file whose real MIME is not on the allow-list is skipped with a warning', function () {
    config(['attachments.allowed_mime_types' => ['application/pdf']]);

    $run = runDocumentBundlesImport([[
        'id' => 11,
        'title' => 'Con MIME non ammesso',
        'files' => [legacyDocumentBundleFile(7, 'script.sh', "#!/bin/sh\necho hi\n", ['mime_type' => 'application/pdf'])],
    ]]);

    $bundle = DocumentBundle::query()->where('old_id', 11)->first();
    expect($bundle->attachments()->count())->toBe(0);

    expect(collect($run->report)->firstWhere('level', 'warning')['message'])->toContain('not allowed');
});

// ---------------------------------------------------------------------------
// name collision / row failure
// ---------------------------------------------------------------------------

it('AC-018: a name collision is resolved by a numeric suffix, never a row error', function () {
    DocumentBundle::factory()->create(['name' => 'Duplicato']);

    $run = runDocumentBundlesImport([['id' => 12, 'title' => 'Duplicato', 'files' => []]]);

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0);

    $bundle = DocumentBundle::query()->where('old_id', 12)->first();
    expect($bundle->name)->toBe('Duplicato (2)');
});

it('AC-018: a row with no title fails without blocking the rest of the run', function () {
    $run = runDocumentBundlesImport([
        ['id' => 13, 'title' => '', 'files' => []],
        ['id' => 14, 'title' => 'Valido', 'files' => []],
    ]);

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(1)
        ->and(DocumentBundle::query()->where('old_id', 14)->exists())->toBeTrue()
        ->and(DocumentBundle::query()->where('old_id', 13)->exists())->toBeFalse();
});
