<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\FieldPermission;
use App\Authorization\ResourceAuthorization;
use App\Models\User;
use App\Services\Table\CellValueValidator;
use App\Tables\TableDefinition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Generic, domain-driven inline cell-editing engine (spec 0053, extended by
 * spec 0054 for relation columns): the single guard chain
 * PATCH /api/tables/{domain}/rows/{row} runs through, mirroring
 * TableService/TableBulkDeleteService — one implementation, every domain
 * inherits it through the TableDefinition contract.
 *
 * The GET /columns `editable` flag (AbstractTableDefinition::editableColumnIds())
 * is a UI hint only: this service never trusts it and re-derives every guard
 * against the REAL row (D-2), in the exact order below, so a 422 (structural:
 * unknown/undeclared column, bad value) is never confused with a 403
 * (authorization: row-level Policy, or the DB field-permission matrix).
 *
 * Spec 0054, D-1/D-3: a RELATION column's field-permission key and its
 * WRITE column are `editableField` (e.g. `operator_id`) when declared, never
 * the display column id (e.g. `operator`) the client submits.
 */
class TableCellUpdateService
{
    public function __construct(
        private readonly TableService $tableService,
        private readonly CellValueValidator $valueValidator,
    ) {}

    /**
     * @return array<string, mixed> the updated row, mapped exactly like POST /rows (D-9)
     *
     * @throws ModelNotFoundException row not in $definition->baseQuery() scope (404, D-5)
     * @throws AuthorizationException actor lacks row-level update rights, or the field is DB-locked for them (403)
     * @throws ValidationException column not declared editable, or the value fails its derived rules (422)
     */
    public function update(TableDefinition $definition, User $actor, int $rowId, string $columnId, mixed $value, ?string $note = null): array
    {
        // Step 1: resolve the row from the domain's OWN scope (D-5) — never
        // Model::findOrFail(), so tenant/visibility scoping is never bypassed.
        $row = $definition->baseQuery()->findOrFail($rowId);

        // Step 2: row-level authorization (D-4), separate from the column
        // checks below (covers both a bare lack of `{resource}.update` and a
        // domain-specific scope, e.g. RequestManagementTableDefinition).
        if (! $definition->authorizeUpdate($actor, $row)) {
            throw new AuthorizationException;
        }

        // Step 3: structural allow-list (D-1/D-3/D-10) — 422, never a SQL
        // error: an unknown/undeclared column never reaches the query. For a
        // RELATION column (spec 0054, D-1) the WRITE column and the
        // field-permission key are `editableField`, not the display id.
        $column = $this->declaredEditableColumn($definition, $columnId);
        $fieldKey = $column['editableField'] ?? $columnId;

        // Step 3.5 (spec 0054, D-5): `note` is only accepted on a column that
        // explicitly opts in (`notable` => true) — anywhere else, a submitted
        // note is rejected so it never becomes a side-channel write.
        if ($note !== null && ($column['notable'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'note' => [__('A note is not accepted on this column.')],
            ]);
        }

        // Step 4: per-field DB permission (D-3, role_field_permissions) —
        // 403, kept SEPARATE from step 3 so the two failure reasons never
        // collapse onto the same status code.
        $permission = $this->assertFieldEditable($definition, $actor, $row, $fieldKey);

        // Step 4.5 (mandatory-field enforcement, user directive): derived
        // from FieldPermission::$required — a property of the RESOLVED FIELD,
        // never a per-column declaration, so it applies to every mandatory
        // field automatically, including ones activated in the future. Wins
        // over the column's own `nullable` (0053, D-6) when the two disagree
        // — a required field can never be saved null/blank even if its
        // column would otherwise accept null.
        if ($permission->required && $this->isBlank($value)) {
            throw ValidationException::withMessages([
                'value' => [__('This field is required.')],
            ]);
        }

        // Step 5: value validation derived from the column's type (D-6), or
        // — for a relation column (spec 0054, D-2) — existence + scope.
        $validated = $this->valueValidator->validate($column, $value);

        // Step 6: persist (D-7). Audit is automatic via LogsModelActivity
        // unless the definition overrides updateCell() to bypass Eloquent
        // (D-8 — then the override's own responsibility).
        $updated = $definition->updateCell($row, $fieldKey, $validated);

        // Step 7: response = the row re-mapped through baseQuery() (fresh
        // eager loads), same shape as POST /rows (D-9).
        $fresh = $definition->baseQuery()->find($updated->getKey()) ?? $updated;

        return $this->tableService->mapSingleRow($definition, $actor, $fresh);
    }

    /**
     * The raw column declaration, only when it is BOTH declared
     * `'editable' => true` in the catalogue AND fail-safe-eligible (D-3):
     * its resource is registered in config/authorization.php and its
     * field key — `editableField` when declared (spec 0054, D-1), else the
     * column id — matches a real field key in that resource's catalogue.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function declaredEditableColumn(TableDefinition $definition, string $columnId): array
    {
        $column = $this->findColumn($definition, $columnId);

        if ($column === null || ($column['editable'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'column' => [__('Column [:column] is not editable.', ['column' => $columnId])],
            ]);
        }

        $fieldKey = $column['editableField'] ?? $columnId;

        if (! $this->fieldKeyRegistered($definition, $fieldKey)) {
            throw ValidationException::withMessages([
                'column' => [__('Column [:column] is not editable.', ['column' => $columnId])],
            ]);
        }

        return $column;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findColumn(TableDefinition $definition, string $columnId): ?array
    {
        foreach ($definition->columns() as $column) {
            if (($column['id'] ?? null) === $columnId) {
                return $column;
            }
        }

        return null;
    }

    /**
     * D-3 fail-safe: the resource must be registered in
     * config/authorization.php (a) AND $fieldKey must match a real field key
     * in its catalogue (b) — a divergence between column id and field key is
     * never bridged by a translation map, it simply stays non-editable.
     */
    private function fieldKeyRegistered(TableDefinition $definition, string $fieldKey): bool
    {
        $authorization = $this->resolveAuthorization($definition->resource());

        if ($authorization === null) {
            return false;
        }

        foreach ($authorization->fields() as $field) {
            if ($field->key === $fieldKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws AuthorizationException
     */
    private function assertFieldEditable(TableDefinition $definition, User $actor, Model $row, string $fieldKey): FieldPermission
    {
        $authorization = $this->resolveAuthorization($definition->resource());
        $permission = $authorization?->fieldPermissions($actor, $row)[$fieldKey] ?? null;

        if ($permission === null || ! $permission->editable) {
            throw new AuthorizationException;
        }

        return $permission;
    }

    /**
     * A required field rejects null, a blank string (empty or whitespace-only)
     * AND an empty collection — `required` semantics, not `nullable`'s (0053,
     * D-6). The empty-array case is what keeps a mandatory MULTISELECT column
     * (user directive 2026-07-23: `products_of_interest`) from being cleared
     * in-grid, where "no value" is `[]` rather than null.
     */
    private function isBlank(mixed $value): bool
    {
        return $value === null
            || (is_string($value) && trim($value) === '')
            || (is_array($value) && $value === []);
    }

    private function resolveAuthorization(string $resource): ?ResourceAuthorization
    {
        try {
            return app(AuthorizationRegistry::class)->resolve($resource);
        } catch (ModelNotFoundException) {
            return null;
        }
    }
}
