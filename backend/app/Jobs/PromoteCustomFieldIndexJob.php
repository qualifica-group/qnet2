<?php

declare(strict_types=1);

namespace App\Jobs;

use App\CustomFields\CustomFieldIndexDdlBuilder;
use App\CustomFields\Types\EnumDisplay;
use App\Models\CustomFieldDefinition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in performance lane (spec 0021, AC-021, T15): when a
 * CustomFieldDefinition has `is_indexed=true`, provisions on
 * `custom_field_values` either
 *   - a STORED generated column + B-tree index (scalar fields), whose
 *     expression matches Laravel's compiled JSON-path query exactly, so the
 *     EXISTING FieldTypeHandler filter/sort queries
 *     (App\CustomFields\Types\Concerns\Applies*Filter / OrdersByJsonPath)
 *     transparently benefit — zero handler changes; or
 *   - a multi-valued index directly on the JSON array path (MySQL 8.0.17+),
 *     for array-valued fields (enum multiselect / relation cardinality=many).
 *
 * See App\CustomFields\CustomFieldIndexDdlBuilder for the exact SQL and the
 * expression-matching rationale.
 *
 * DRIVER GUARD: a logged no-op on any driver other than mysql/mariadb —
 * sqlite (dev/test) has different generated-column/index semantics and does
 * not need this lane; the field stays fully functional via the plain
 * JSON-path handlers either way (AC-021's "il campo resta funzionante anche
 * senza promozione").
 *
 * PROD-TIME VERIFICATION (cannot be asserted from the sqlite test suite):
 *   EXPLAIN SELECT * FROM custom_field_values
 *     WHERE json_unquote(json_extract(`values`,'$."<key>"')) = 'x';
 *   -> the `key` column of the EXPLAIN output should reference the promoted
 *      index (idx_cfg_<key>), not a full table scan.
 *
 * KNOWN LIMITATION — shared column across entity_types: the generated column
 * is named from the field KEY ALONE (`cfg_<key>`), per the task's naming
 * contract. `key` is unique only PER entity_type, so two definitions on
 * different entity_types sharing the same key will transparently SHARE one
 * physical column/index once either opts into indexing — harmless as long as
 * both resolve to the same SQL type; idempotency (skip if the column already
 * exists) means the FIRST promoted definition's type wins for that key.
 *
 * KNOWN LIMITATION — multi-valued index vs. the existing set filter:
 * App\CustomFields\Types\Concerns\AppliesSetFilter calls Eloquent's
 * `orWhereJsonContains()`, which MySQL's grammar compiles to the
 * 3-argument `JSON_CONTAINS(doc, value, path)` form. MySQL's documented
 * multi-valued-index examples use the 2-argument `JSON_CONTAINS(doc->path,
 * value)` form. Whether the optimizer treats these as equivalent for
 * multi-valued-index matching is NOT verified here (requires a live MySQL
 * EXPLAIN): the index is still provisioned — harmless, and directly usable by
 * MEMBER OF()/JSON_OVERLAPS() queries — but its use by the existing set
 * filter on a multiselect/many-relation field must be confirmed in
 * production before relying on it for performance. No handler change is made
 * to force this (out of this job's scope, per task instructions).
 */
class PromoteCustomFieldIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int, string> */
    private const array SUPPORTED_DRIVERS = ['mysql', 'mariadb'];

    public function __construct(private readonly int $definitionId) {}

    /**
     * Exposed for assertions (e.g. `Queue::assertPushed(..., fn ($job) =>
     * $job->definitionId() === $id)`); the job otherwise never needs to
     * reveal it beyond handle()/rollback().
     */
    public function definitionId(): int
    {
        return $this->definitionId;
    }

    public function handle(CustomFieldIndexDdlBuilder $ddl): void
    {
        // Step 1: driver guard — see class docblock.
        if (! $this->onSupportedDriver()) {
            Log::info('PromoteCustomFieldIndexJob: no-op on non-MySQL driver.', [
                'driver' => DB::connection()->getDriverName(),
                'definition_id' => $this->definitionId,
            ]);

            return;
        }

        // Step 2: resolve the definition; bail if it no longer wants
        // indexing (toggled back off, or deleted, between dispatch and run).
        $definition = CustomFieldDefinition::query()->find($this->definitionId);

        if ($definition === null || ! $definition->is_indexed) {
            return;
        }

        // Step 3: scalar generated column + index, or multi-valued index.
        $column = $ddl->columnName($definition->key);

        if ($this->isMultiValued($definition)) {
            $this->provisionMultiValuedIndex($ddl, $definition, $column);

            return;
        }

        $this->provisionGeneratedColumn($ddl, $definition, $column);
    }

    /**
     * Reversible counterpart, exposed for a future decommission path — NOT
     * auto-wired to definition delete, which already handles VALUE cleanup
     * independently (App\Services\CustomFieldService::delete()) and is out of
     * this job's scope.
     */
    public function rollback(CustomFieldIndexDdlBuilder $ddl, CustomFieldDefinition $definition): void
    {
        if (! $this->onSupportedDriver()) {
            return;
        }

        $column = $ddl->columnName($definition->key);
        $index = $ddl->indexName($column);

        if (Schema::hasIndex(CustomFieldIndexDdlBuilder::TABLE, $index)) {
            DB::statement($ddl->dropIndexStatement($index));
        }

        if (! $this->isMultiValued($definition) && Schema::hasColumn(CustomFieldIndexDdlBuilder::TABLE, $column)) {
            DB::statement($ddl->dropColumnStatement($column));
        }
    }

    private function onSupportedDriver(): bool
    {
        return in_array(DB::connection()->getDriverName(), self::SUPPORTED_DRIVERS, true);
    }

    private function provisionGeneratedColumn(CustomFieldIndexDdlBuilder $ddl, CustomFieldDefinition $definition, string $column): void
    {
        if (! Schema::hasColumn(CustomFieldIndexDdlBuilder::TABLE, $column)) {
            DB::statement($ddl->addGeneratedColumnStatement($column, $ddl->scalarSqlType($definition), $definition->key));
        }

        $index = $ddl->indexName($column);

        if (! Schema::hasIndex(CustomFieldIndexDdlBuilder::TABLE, $index)) {
            DB::statement($ddl->addIndexStatement($index, $column));
        }
    }

    private function provisionMultiValuedIndex(CustomFieldIndexDdlBuilder $ddl, CustomFieldDefinition $definition, string $column): void
    {
        $index = $ddl->indexName($column);

        if (Schema::hasIndex(CustomFieldIndexDdlBuilder::TABLE, $index)) {
            return;
        }

        DB::statement($ddl->addMultiValuedIndexStatement($index, $definition->key, $ddl->multiValuedElementType($definition)));
    }

    private function isMultiValued(CustomFieldDefinition $definition): bool
    {
        if ($definition->type === 'enum') {
            return EnumDisplay::isMultiValue($definition->config);
        }

        if ($definition->type === 'relation') {
            return ($definition->relation_target['cardinality'] ?? 'one') === 'many';
        }

        return false;
    }
}
