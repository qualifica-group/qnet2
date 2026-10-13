<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\PurchaseOrderStatusLabel;
use App\Models\User;
use App\Tables\PurchaseOrderStatuses\PurchaseOrderStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `purchase-order-statuses` presentation catalogue
 * (spec 0248): seven system rows, sorted by sort_order, no delete action.
 */
class PurchaseOrderStatusesTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'purchase-order-statuses';
    }

    /**
     * @return class-string<PurchaseOrderStatusLabel>
     */
    public function modelClass(): string
    {
        return PurchaseOrderStatusLabel::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // derives PurchaseOrderStatusLabelPolicy::viewAny from modelClass().

    /**
     * @return Builder<PurchaseOrderStatusLabel>
     */
    public function baseQuery(): Builder
    {
        return PurchaseOrderStatusLabel::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return PurchaseOrderStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return PurchaseOrderStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return PurchaseOrderStatusColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'sort_order', 'direction' => 'asc'],
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
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var PurchaseOrderStatusLabel $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'color' => $row->color,
            'icon' => $row->icon,
            'sort_order' => $row->sort_order,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var PurchaseOrderStatusLabel $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }
}
