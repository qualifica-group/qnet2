<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

// Schema for the `opportunity-workflows` -> `quote-workflows` rename and the
// `quote_status_id` -> `quote_workflow_status_id` swap (spec 0083
// data_contract MIGRATION block, AC-001..AC-003). AC-004 (rollback) and the
// permission rename/prune migration live in QuoteWorkflowMigrationTest.php:
// they replay migrations directly, which this file's per-test transaction
// (RefreshDatabase) would break.

uses(RefreshDatabase::class);

it('renames the workflow configurator tables and drops the flat quote-statuses table (AC-001)', function () {
    expect(Schema::hasTable('quote_workflows'))->toBeTrue()
        ->and(Schema::hasTable('quote_workflow_criteria'))->toBeTrue()
        ->and(Schema::hasTable('quote_workflow_statuses'))->toBeTrue();

    expect(Schema::hasTable('opportunity_workflows'))->toBeFalse()
        ->and(Schema::hasTable('opportunity_workflow_criteria'))->toBeFalse()
        ->and(Schema::hasTable('opportunity_workflow_statuses'))->toBeFalse()
        ->and(Schema::hasTable('quote_statuses'))->toBeFalse();
});

it('replaces quotes.quote_status_id with a NOT NULL quote_workflow_status_id FK (AC-002)', function () {
    expect(Schema::hasColumn('quotes', 'quote_status_id'))->toBeFalse()
        ->and(Schema::hasColumn('quotes', 'quote_workflow_status_id'))->toBeTrue();

    $column = collect(Schema::getColumns('quotes'))->firstWhere('name', 'quote_workflow_status_id');
    expect($column['nullable'])->toBeFalse();

    $foreignKey = collect(Schema::getForeignKeys('quotes'))
        ->first(fn (array $fk) => $fk['columns'] === ['quote_workflow_status_id']);
    expect($foreignKey)->not->toBeNull()
        ->and($foreignKey['foreign_table'])->toBe('quote_workflow_statuses');
});

it('drops opportunities.opportunity_workflow_status_id entirely (AC-003)', function () {
    expect(Schema::hasColumn('opportunities', 'opportunity_workflow_status_id'))->toBeFalse();
});
