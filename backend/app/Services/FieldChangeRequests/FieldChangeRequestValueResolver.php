<?php

declare(strict_types=1);

namespace App\Services\FieldChangeRequests;

use App\FieldChangeRequests\ProtectedField;
use App\Models\User;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves everything the field-change-request write path needs to know
 * about a (resource, field, subject) triple, entirely through the
 * TableDefinition contract (F-5/F-6): no domain-specific model class or
 * attribute is ever named here. That is what keeps the whole system reusable
 * for a SECOND protected field on a THIRD resource (AC-054) without touching
 * this class — a relation column's label is read through the RELATED
 * resource's own `mapRow()` "name" projection, never a hardcoded model.
 */
final class FieldChangeRequestValueResolver
{
    public function __construct(private readonly TableRegistry $tables) {}

    /**
     * @throws ModelNotFoundException the resource is not a registered TableRegistry domain (404)
     */
    public function definitionFor(string $resource): TableDefinition
    {
        return $this->tables->resolve($resource);
    }

    /**
     * @throws ModelNotFoundException the record is out of the domain's own baseQuery() scope (404, AC-022)
     */
    public function record(TableDefinition $definition, int $subjectId): Model
    {
        return $definition->baseQuery()->findOrFail($subjectId);
    }

    /**
     * The protected field's own column, straight from the catalogue — null
     * when it no longer matches any declared column (fail-safe: the
     * protected-fields config and the table's own catalogue have drifted).
     *
     * @return array<string, mixed>|null
     */
    public function columnConfig(TableDefinition $definition, ProtectedField $protectedField): ?array
    {
        foreach ($definition->columns() as $column) {
            if (($column['id'] ?? null) === $protectedField->column) {
                return $column;
            }
        }

        return null;
    }

    /**
     * The field's raw current value, read straight off the model attribute —
     * the SAME key TableCellUpdateService itself writes to (`field`, e.g.
     * `source_id`), never the column's projected display shape (which for a
     * relation column would be a `{id, name}` array rather than the bare id).
     */
    public function currentValue(Model $record, ProtectedField $protectedField): mixed
    {
        return $record->getAttribute($protectedField->field);
    }

    /**
     * A human-readable label for $value (current or requested alike): for a
     * relation column, the related row's own `mapRow()` "name" (resolved
     * through TableRegistry again — the genericity AC-054 tests); for
     * anything else, the scalar cast to string. Null in, null out.
     *
     * @param  array<string, mixed>  $columnConfig
     */
    public function labelFor(array $columnConfig, mixed $value, User $actor): ?string
    {
        if ($value === null) {
            return null;
        }

        $relationResource = $columnConfig['relation']['resource'] ?? null;

        if (! is_string($relationResource) || ! is_scalar($value)) {
            return is_scalar($value) ? (string) $value : null;
        }

        return $this->relationLabel($relationResource, $value, $actor);
    }

    /**
     * The record's own display label, read from `mapRow()`'s "name"
     * projection (the convention every row-grid domain follows) — falls back
     * to the raw key when the domain's row carries none.
     */
    public function subjectLabel(TableDefinition $definition, Model $record, User $actor): string
    {
        $row = $definition->mapRow($actor, $record);

        return isset($row['name']) && is_scalar($row['name']) ? (string) $row['name'] : (string) $record->getKey();
    }

    /**
     * The record's deep-link: the protected field's own `record_path`
     * (config-declared, F-6) plus the subject id — a plain string built from
     * config, never a query.
     */
    public function subjectPath(ProtectedField $protectedField, int|string $subjectId): string
    {
        return "{$protectedField->recordPath}/{$subjectId}";
    }

    /**
     * subjectLabel(), tolerant of a subject that has since vanished (used by
     * a notification built AFTER the write transaction already committed):
     * falls back to the raw id rather than letting a 404 bubble into a
     * notification-dispatch path that must never fail the request.
     */
    public function subjectLabelOrFallback(string $resource, int $subjectId, User $actor): string
    {
        try {
            $definition = $this->definitionFor($resource);
            $record = $this->record($definition, $subjectId);
        } catch (ModelNotFoundException) {
            return (string) $subjectId;
        }

        return $this->subjectLabel($definition, $record, $actor);
    }

    /**
     * Whether two already-resolved values are the SAME proposed/persisted
     * value (AC-019: a no-op request; D-4/AC-030: the conflict guard at
     * approval time) — compared through their JSON encoding so a relation id
     * (int) and a future collection value (array) both compare correctly
     * without this class caring which shape it is.
     */
    public function sameValue(mixed $a, mixed $b): bool
    {
        return json_encode($a) === json_encode($b);
    }

    private function relationLabel(string $relationResource, mixed $value, User $actor): ?string
    {
        try {
            $relatedDefinition = $this->tables->resolve($relationResource);
        } catch (ModelNotFoundException) {
            return null;
        }

        $relatedRecord = $relatedDefinition->baseQuery()->find($value);

        if ($relatedRecord === null) {
            return null;
        }

        $row = $relatedDefinition->mapRow($actor, $relatedRecord);

        return isset($row['name']) && is_scalar($row['name']) ? (string) $row['name'] : null;
    }
}
