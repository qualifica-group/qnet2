<?php

use App\Authorization\AuthorizationRegistry;
use App\Tables\AbstractTableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Guard tests for the inline cell-editing engine (spec 0053 AC-012/AC-013,
 * extended by spec 0054 AC-016 to resolve on `editableField` when a column
 * declares one — spec 0054, D-1): iterate EVERY domain registered in
 * config/tables.php, so a future column declared `editable` without the
 * required fail-safe eligibility (D-3) or a real, fillable, non-derived DB
 * column (D-10) is caught here, listing every divergence rather than
 * failing on an opaque assertion.
 */
it('AC-012: every editable column has its resource registered in config/authorization.php with a matching field key', function () {
    $divergences = [];

    foreach (array_keys(config('tables.definitions', [])) as $domain) {
        $definition = app(TableRegistry::class)->resolveRaw($domain);
        $resource = $definition->resource();

        $editableColumns = array_filter(
            $definition->columns(),
            static fn (array $column): bool => ($column['editable'] ?? false) === true,
        );

        if ($editableColumns === []) {
            continue;
        }

        try {
            $authorization = app(AuthorizationRegistry::class)->resolve($resource);
        } catch (ModelNotFoundException) {
            foreach ($editableColumns as $column) {
                $divergences[] = "{$domain}.{$column['id']}: resource `{$resource}` not registered in config/authorization.php";
            }

            continue;
        }

        $fieldKeys = array_column($authorization->fields(), 'key');

        foreach ($editableColumns as $column) {
            // Spec 0054, D-1: a relation column's permission key is
            // `editableField`, not the display column id.
            $fieldKey = $column['editableField'] ?? $column['id'];

            if (! in_array($fieldKey, $fieldKeys, true)) {
                $divergences[] = "{$domain}.{$column['id']}: no matching field key `{$fieldKey}` in `{$resource}`'s authorization catalogue";
            }
        }
    }

    expect($divergences)->toBe([]);
});

it('AC-013: every editable column is a real, fillable DB column (or the definition overrides updateCell)', function () {
    $divergences = [];

    foreach (array_keys(config('tables.definitions', [])) as $domain) {
        $definition = app(TableRegistry::class)->resolveRaw($domain);

        /** @var class-string<Model> $modelClass */
        $modelClass = $definition->modelClass();
        $model = new $modelClass;
        $table = $model->getTable();
        $fillable = $model->getFillable();

        $overridesUpdateCell = (new ReflectionMethod($definition, 'updateCell'))
            ->getDeclaringClass()->getName() !== AbstractTableDefinition::class;

        foreach ($definition->columns() as $column) {
            if (($column['editable'] ?? false) !== true) {
                continue;
            }

            if ($overridesUpdateCell) {
                continue; // the override owns its own write path (D-7).
            }

            // Spec 0054, D-1/D-3: the column ACTUALLY WRITTEN is
            // `editableField` for a relation column, else the column id.
            $fieldKey = $column['editableField'] ?? $column['id'];

            if (($column['hasFilterValues'] ?? true) === false) {
                $divergences[] = "{$domain}.{$column['id']}: hasFilterValues=false (a derived/aggregate column) declared editable";

                continue;
            }

            if (! Schema::hasColumn($table, $fieldKey)) {
                $divergences[] = "{$domain}.{$column['id']}: no real DB column `{$fieldKey}` on `{$table}`";

                continue;
            }

            if (! in_array($fieldKey, $fillable, true)) {
                $divergences[] = "{$domain}.{$column['id']}: `{$fieldKey}` not in {$modelClass}::\$fillable";
            }
        }
    }

    expect($divergences)->toBe([]);
});

it('AC-016: a domain with no editable column declares none (every column emits editable: false)', function () {
    // `sectors` declares no editable column in this round (out of the 5
    // domains this spec activates) — the motor changes no behaviour for it.
    $definition = app(TableRegistry::class)->resolveRaw('sectors');

    foreach ($definition->columns() as $column) {
        expect($column['editable'] ?? false)->toBeFalse();
    }
});
