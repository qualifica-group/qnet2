<?php

namespace App\Tables;

use App\Models\ProductTypology;
use App\Models\User;
use App\Services\ProductTypologyService;
use App\Tables\ProductTypologies\ProductTypologyColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `product-typologies` domain (spec 0099).
 *
 * Every column (name, code, description, created_at, updated_at) is a real
 * DB column handled entirely by the generic engine.
 */
class ProductTypologiesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly ProductTypologyService $service) {}

    public function domain(): string
    {
        return 'product-typologies';
    }

    /**
     * @return class-string<ProductTypology>
     */
    public function modelClass(): string
    {
        return ProductTypology::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives ProductTypologyPolicy::viewAny
    // from modelClass() (product-typologies.viewAny).

    /**
     * @return Builder<ProductTypology>
     */
    public function baseQuery(): Builder
    {
        return ProductTypology::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ProductTypologyColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ProductTypologyColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ProductTypologyColumnCatalog::actions();
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
     * Map a ProductTypology to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var ProductTypology $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'code' => $row->code,
            'description' => $row->description,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via ProductTypologyPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var ProductTypology $row */
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
     * Delegate to ProductTypologyService::delete() so the generic bulk-delete
     * endpoint respects the SAME guard (spec 0099, D-8) as the single DELETE
     * /product-typologies/{productTypology} endpoint (AC-016).
     */
    public function deleteModel(Model $model): void
    {
        /** @var ProductTypology $model */
        $this->service->delete($model);
    }
}
