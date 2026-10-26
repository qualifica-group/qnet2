<?php

use App\Authorization\LegacyIdVisibility;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Jobs\GenerateExportJob;
use App\Models\BusinessFunction;
use App\Models\ExportRun;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\StubExportTableDefinition;

uses(RefreshDatabase::class);

function runGenerateExportJob(ExportRun $run): void
{
    (new GenerateExportJob($run->id))->handle(app(ExportService::class));
}

function csvRows(string $csv): array
{
    $lines = array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n")));

    return array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
}

// ---------------------------------------------------------------------------
// AC-005/AC-006/AC-007 — GenerateExportJob generation (CSV)
// ---------------------------------------------------------------------------

it('writes ONLY the requested columns, in the requested order, with the requested headers (CSV)', function () {
    config(['tables.definitions' => ['stub-exports' => StubExportTableDefinition::class]]);
    Storage::fake('local');

    $actor = User::factory()->create();
    BusinessFunction::factory()->create(['name' => 'Sales', 'is_business_unit' => true]);
    BusinessFunction::factory()->create(['name' => 'Support', 'is_business_unit' => false]);

    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-exports',
        'format' => ExportFormat::Csv,
        'state' => [
            'columns' => [
                ['colId' => 'is_business_unit', 'header' => 'Unit?'],
                ['colId' => 'name', 'header' => 'Name'],
            ],
            'sortModel' => [['colId' => 'name', 'sort' => 'asc']],
        ],
    ]);

    runGenerateExportJob($run);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(ExportStatus::Completed)
        ->and($fresh->row_count)->toBe(2)
        ->and($fresh->file_path)->not->toBeNull();

    Storage::disk('local')->assertExists($fresh->file_path);

    $rows = csvRows(Storage::disk('local')->get($fresh->file_path));

    expect($rows[0])->toBe(['Unit?', 'Name']) // header, in the requested order
        ->and($rows[1])->toBe(['Yes', 'Sales']) // sorted asc by name
        ->and($rows[2])->toBe(['No', 'Support']);
});

it('writes the legacy old_id the grid shows, only for an actor who may see it', function (bool $mayViewLegacyId, string $expected) {
    config(['tables.definitions' => ['stub-exports' => StubExportTableDefinition::class]]);
    Storage::fake('local');

    $actor = User::factory()->create();
    if ($mayViewLegacyId) {
        $actor->givePermissionTo(Permission::findOrCreate(LegacyIdVisibility::PERMISSION));
    }
    BusinessFunction::factory()->create(['name' => 'Sales'])->forceFill(['old_id' => 4321])->save();

    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-exports',
        'format' => ExportFormat::Csv,
        'state' => ['columns' => [['colId' => 'old_id', 'header' => 'Old ID'], ['colId' => 'name', 'header' => 'Name']]],
    ]);

    runGenerateExportJob($run);

    $rows = csvRows(Storage::disk('local')->get($run->fresh()->file_path));

    expect($rows[1])->toBe([$expected, 'Sales']);
})->with([
    'with legacy-ids.view' => [true, '4321'],
    'without legacy-ids.view' => [false, ''],
]);

it('respects filterModel + global search identical to the grid', function () {
    config(['tables.definitions' => ['stub-exports' => StubExportTableDefinition::class]]);
    Storage::fake('local');

    $actor = User::factory()->create();
    BusinessFunction::factory()->create(['name' => 'Sales Unit', 'is_business_unit' => true]);
    BusinessFunction::factory()->create(['name' => 'Support', 'is_business_unit' => false]);
    BusinessFunction::factory()->create(['name' => 'Sales Ops', 'is_business_unit' => false]);

    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-exports',
        'format' => ExportFormat::Csv,
        'state' => [
            'columns' => [['colId' => 'name', 'header' => 'Name']],
            'search' => 'sales',
        ],
    ]);

    runGenerateExportJob($run);

    $rows = csvRows(Storage::disk('local')->get($run->fresh()->file_path));

    expect($rows)->toHaveCount(3) // header + 2 matches
        ->and($run->fresh()->row_count)->toBe(2);
});

it('formats boolean/datetime/tags values per column type', function () {
    config(['tables.definitions' => ['stub-exports' => StubExportTableDefinition::class]]);
    Storage::fake('local');
    app()->setLocale('en');

    $actor = User::factory()->create();
    BusinessFunction::factory()->create(['name' => 'Sales Unit', 'is_business_unit' => true]);

    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-exports',
        'format' => ExportFormat::Csv,
        'state' => [
            'columns' => [
                ['colId' => 'is_business_unit', 'header' => 'Unit?'],
                ['colId' => 'tags', 'header' => 'Tags'],
                ['colId' => 'created_at', 'header' => 'Created'],
            ],
        ],
    ]);

    runGenerateExportJob($run);

    $rows = csvRows(Storage::disk('local')->get($run->fresh()->file_path));

    expect($rows[1][0])->toBe('Yes')
        ->and($rows[1][1])->toBe('Sales; Unit') // tags join('; ')
        ->and($rows[1][2])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});

it('writes a valid, re-openable .xlsx file (streaming writer)', function () {
    config(['tables.definitions' => ['stub-exports' => StubExportTableDefinition::class]]);
    Storage::fake('local');

    $actor = User::factory()->create();
    BusinessFunction::factory()->create(['name' => 'Sales']);

    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-exports',
        'format' => ExportFormat::Xlsx,
        'state' => ['columns' => [['colId' => 'name', 'header' => 'Name']]],
    ]);

    runGenerateExportJob($run);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(ExportStatus::Completed);

    $absolutePath = Storage::disk('local')->path($fresh->file_path);
    $reader = new XlsxReader;
    $reader->open($absolutePath);

    $values = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $values[] = $row->toArray();
        }
    }
    $reader->close();

    expect($values)->toBe([['Name'], ['Sales']]);
});

it('moves the run to failed on an unhandled exception (e.g. unknown domain)', function () {
    config(['tables.definitions' => []]);
    Storage::fake('local');

    $actor = User::factory()->create();
    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-exports',
        'state' => ['columns' => [['colId' => 'name', 'header' => 'Name']]],
    ]);

    expect(fn () => runGenerateExportJob($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(ExportStatus::Failed);
});
