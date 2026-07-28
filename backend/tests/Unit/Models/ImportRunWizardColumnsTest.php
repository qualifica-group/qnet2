<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 — wizard columns additive migration (up/down) + existing schema
// ---------------------------------------------------------------------------

it('adds the wizard columns to import_runs on top of the legacy schema', function () {
    expect(Schema::hasTable('import_runs'))->toBeTrue();

    // Legacy columns (spec 0012) are untouched by the additive migration.
    expect(Schema::hasColumns('import_runs', [
        'id', 'resource', 'user_id', 'status', 'original_filename', 'stored_path',
        'total_rows', 'valid_rows', 'invalid_rows', 'imported_rows',
        'error_report_path', 'preview', 'created_at', 'updated_at',
    ]))->toBeTrue();

    // New wizard columns (spec 0033).
    expect(Schema::hasColumns('import_runs', [
        'detected_columns', 'column_mapping', 'global_config', 'dedup_strategy',
        'warning_rows', 'duplicate_rows', 'modified_rows', 'notified_at', 'error_count',
    ]))->toBeTrue();
});

it('new wizard columns default to 0 / null at the database level', function () {
    // Eloquent does not re-fetch DB-level column defaults into the in-memory
    // model after insert (only the auto-increment PK is synced), so assert
    // against the persisted row via fresh().
    $run = ImportRun::factory()->create()->fresh();

    expect($run->warning_rows)->toBe(0)
        ->and($run->duplicate_rows)->toBe(0)
        ->and($run->modified_rows)->toBe(0)
        ->and($run->error_count)->toBe(0)
        ->and($run->detected_columns)->toBeNull()
        ->and($run->column_mapping)->toBeNull()
        ->and($run->global_config)->toBeNull()
        ->and($run->dedup_strategy)->toBeNull()
        ->and($run->notified_at)->toBeNull();
});

it('casts detected_columns/column_mapping/global_config to array, notified_at to datetime', function () {
    $run = ImportRun::factory()->create([
        'detected_columns' => [['name' => 'Email', 'index' => 0, 'duplicate' => false]],
        'column_mapping' => ['Email' => 'email'],
        'global_config' => ['campaign_id' => 1],
        'dedup_strategy' => 'create_new',
        'notified_at' => now(),
    ]);

    $fresh = $run->fresh();

    expect($fresh->detected_columns)->toBeArray()
        ->and($fresh->detected_columns[0]['name'])->toBe('Email')
        ->and($fresh->column_mapping)->toBe(['Email' => 'email'])
        ->and($fresh->global_config)->toBe(['campaign_id' => 1])
        ->and($fresh->dedup_strategy)->toBe('create_new')
        ->and($fresh->notified_at)->toBeInstanceOf(Carbon::class);
});

it('the legacy status cases and the new wizard status cases coexist on the enum', function () {
    expect(ImportStatus::Validating->value)->toBe('validating')
        ->and(ImportStatus::AwaitingConfirmation->value)->toBe('awaiting_confirmation')
        ->and(ImportStatus::Analyzing->value)->toBe('analyzing')
        ->and(ImportStatus::Configuring->value)->toBe('configuring')
        ->and(ImportStatus::Staging->value)->toBe('staging')
        ->and(ImportStatus::Reviewing->value)->toBe('reviewing')
        ->and(ImportStatus::Processing->value)->toBe('processing')
        ->and(ImportStatus::Completed->value)->toBe('completed')
        ->and(ImportStatus::Failed->value)->toBe('failed');
});

it('has a rows() relation to ImportRunRow', function () {
    $run = ImportRun::factory()->create();

    expect($run->rows())->toBeInstanceOf(HasMany::class);
});
