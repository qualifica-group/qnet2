<?php

namespace App\Migrations;

use Illuminate\Database\Eloquent\Model;

/**
 * The outcome of importing ONE external record (spec 0013): either it was
 * SKIPPED (its `old_id` already exists — idempotent re-import), CREATED or, when
 * the run asked to update the already imported records (spec 0372), UPDATED.
 * Both may carry non-fatal warnings (e.g. an unresolved relational reference,
 * or a note about relations back-filled on a self-healing re-import) to
 * surface in the run's report. A thrown exception from
 * MigrationSource::processRow (via AbstractMigrationSource) is a distinct,
 * FAILED outcome handled by the caller, never represented here.
 *
 * `$model` is the persisted record a CREATED outcome wrote (or adopted), so
 * AbstractMigrationSource::importRow() can graft the generic custom-field
 * write onto it (spec 0021 auto-persistence); a SKIPPED (deduped) outcome
 * never carries one, so a re-import never rewrites custom field values.
 */
final readonly class MigrationRowOutcome
{
    /**
     * @param  array<int, string>  $warnings
     */
    private function __construct(
        public bool $skipped,
        public bool $updated,
        public array $warnings,
        public ?Model $model,
    ) {}

    /**
     * @param  array<int, string>  $warnings
     */
    public static function created(array $warnings = [], ?Model $model = null): self
    {
        return new self(skipped: false, updated: false, warnings: $warnings, model: $model);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    public static function skipped(array $warnings = []): self
    {
        return new self(skipped: true, updated: false, warnings: $warnings, model: null);
    }

    /**
     * An existing record was realigned with the legacy (spec 0372). No model: the
     * custom field values of an existing record are never rewritten.
     *
     * @param  array<int, string>  $warnings
     */
    public static function updated(array $warnings = []): self
    {
        return new self(skipped: false, updated: true, warnings: $warnings, model: null);
    }
}
