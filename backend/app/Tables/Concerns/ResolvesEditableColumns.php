<?php

namespace App\Tables\Concerns;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourceAuthorization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/**
 * Inline cell-editing defaults (spec 0053): the per-column allow-list
 * (editableColumnIds), the per-row Policy check (authorizeUpdate) and the
 * default write (updateCell). Split out of AbstractTableDefinition to stay
 * within the file-size budget (engineering.md §6) — this trait is the WHOLE
 * of spec 0053's default behaviour, a single cohesive concern.
 *
 * The using class must implement TableDefinition (resource()/modelClass()/
 * columnsWithDefaultId() from InjectsDefaultIdColumn) — not declared here to
 * avoid a redundant interface reference; AbstractTableDefinition already
 * satisfies it.
 */
trait ResolvesEditableColumns
{
    /**
     * Column ids where inline cell-editing is allowed for $actor: declared
     * `'editable' => true` in the catalogue AND `{resource}.update` AND the
     * per-field DB permission (role_field_permissions, via
     * AuthorizationRegistry) all allow it. Fail-safe (D-3): a resource
     * unregistered in config/authorization.php, or a column id with no
     * matching field key in that resource's catalogue, is never editable.
     *
     * A UI hint only (drives the `editable` flag GET /columns emits) — the
     * PATCH endpoint never trusts this list, it re-derives its own guards
     * against the REAL row (D-2).
     *
     * A RELATION column needs NO `{relation.resource}.viewAny` on top: since
     * ADR 0011 was amended (2026-07-31) the pickers are gated by
     * `auth:sanctum` alone, so the actor who may update this resource can
     * always populate the dropdown. Re-adding that check would make the cell
     * read-only for exactly the actor the amendment set out to unblock.
     *
     * @return array<int, string>
     */
    public function editableColumnIds(User $actor): array
    {
        if (! $actor->can("{$this->resource()}.update")) {
            return [];
        }

        $authorization = $this->resolveFieldAuthorization();

        if ($authorization === null) {
            return [];
        }

        $fieldKeys = array_column($authorization->fields(), 'key');
        $permissions = $authorization->fieldPermissions($actor, $this->editableContextModel());

        $ids = [];

        foreach ($this->columnsWithDefaultId() as $column) {
            $id = $column['id'];
            // Spec 0054, D-1: a RELATION column's field-permission key is
            // `editableField` (the DB column actually written), not the
            // display column id — falls back to $id for every 0053 column.
            $fieldKey = $column['editableField'] ?? $id;

            if (($column['editable'] ?? false) !== true || ! in_array($fieldKey, $fieldKeys, true)) {
                continue; // declaration missing, or D-3(b): unknown field key.
            }

            if ($permissions[$fieldKey]->editable ?? false) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Fail-safe default per-row authorization for inline cell-editing (D-4):
     * delegates to the model's Policy `update`, same pattern as
     * authorizeViewAny(). Override when the domain's update ability is NOT
     * governed by modelClass()'s own Policy (e.g. a definition whose model's
     * Policy maps to a DIFFERENT permission prefix than its own resource()).
     */
    public function authorizeUpdate(User $actor, Model $row): bool
    {
        return Gate::forUser($actor)->allows('update', $row);
    }

    /**
     * Default cell write (D-7): a plain, single-column mass-assignment
     * update. Requires $columnId to be in $row's $fillable, so a misdeclared
     * editable column fails LOUDLY (a mass-assignment exception) instead of
     * silently no-op-ing. Concrete definitions override when the write must
     * go through a domain Service instead (business rules, a column outside
     * $fillable) — an override that bypasses this Eloquent update() cycle
     * must emit the activity-log entry itself (D-8).
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        $row->update([$columnId => $value]);

        return $row->fresh() ?? $row;
    }

    /**
     * This definition's ResourceAuthorization, or null when its resource()
     * is not registered in config/authorization.php (D-3a fail-safe: no
     * registration → no column of this domain is ever editable).
     */
    private function resolveFieldAuthorization(): ?ResourceAuthorization
    {
        try {
            return app(AuthorizationRegistry::class)->resolve($this->resource());
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * A transient, unpersisted instance of modelClass() standing in for "an
     * existing row" when resolving the field-permission ceiling for GET
     * /columns (D-2): editableColumnIds() runs once per request with no
     * specific row in scope, but the ceiling must resolve its UPDATE (not
     * create) rules. Every fieldPermissionCeiling() in this codebase
     * branches only on `$model === null` vs not, never on the model's actual
     * attributes, so a fresh instance is a safe, row-agnostic stand-in — the
     * PATCH endpoint itself always re-checks against the REAL row (D-2).
     */
    private function editableContextModel(): Model
    {
        $modelClass = $this->modelClass();

        return new $modelClass;
    }

    /**
     * columnsWithDefaultId() itself is NOT declared abstract here: it is a
     * `private` method on the sibling InjectsDefaultIdColumn trait, and a
     * private method cannot satisfy an abstract requirement declared by
     * another trait composed into the same class — PHP only needs it to
     * exist at runtime, which AbstractTableDefinition already guarantees.
     */
    abstract public function resource(): string;

    /**
     * @return class-string<Model>
     */
    abstract public function modelClass(): string;
}
