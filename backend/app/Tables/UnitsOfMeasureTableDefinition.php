<?php

namespace App\Tables;

use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\UnitOfMeasureService;
use App\Tables\UnitsOfMeasure\UnitOfMeasureColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `units-of-measure` domain (spec 0088).
 *
 * Every column (name, symbol, code, description, created_at, updated_at) is
 * a real DB column handled entirely by the generic engine.
 */
class UnitsOfMeasureTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly UnitOfMeasureService $service) {}

    public function domain(): string
    {
        return 'units-of-measure';
    }

    /**
     * @return class-string<UnitOfMeasure>
     */
    public function modelClass(): string
    {
        return UnitOfMeasure::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives UnitOfMeasurePolicy::viewAny
    // from modelClass() (units-of-measure.viewAny).

    /**
     * @return Builder<UnitOfMeasure>
     */
    public function baseQuery(): Builder
    {
        return UnitOfMeasure::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return UnitOfMeasureColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return UnitOfMeasureColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return UnitOfMeasureColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'name', 'direction' => 'asc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Map a UnitOfMeasure to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var UnitOfMeasure $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'symbol' => $row->symbol,
            'code' => $row->code,
            'description' => $row->description,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via UnitOfMeasurePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var UnitOfMeasure $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to UnitOfMeasureService::delete() so the generic bulk-delete
     * endpoint respects the SAME guard (spec 0088, D-7) as the single DELETE
     * /units-of-measure/{unitOfMeasure} endpoint (AC-017).
     */
    public function deleteModel(Model $model): void
    {
        /** @var UnitOfMeasure $model */
        $this->service->delete($model);
    }
}
