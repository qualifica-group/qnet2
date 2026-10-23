<?php

namespace App\Tables\Stock;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use App\Models\User;
use App\Policies\StockPolicy;
use App\Services\Stock\StockMovementService;
use App\Support\OperationalSiteLabel;
use App\Tables\AbstractTableDefinition;
use App\Tables\PurchaseRequests\PurchaseRequestColumnCatalog as Catalog;
use App\Tables\PurchaseRequests\PurchaseRequestDerivedQuery as Derived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Table definition for the `stock-movements` domain (spec 0307, D-19): the
 * append-only register, newest first. No action: movements are never edited
 * or deleted.
 */
class StockMovementsTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'stock.movements';

    /** Sortable related columns: id => [related table, foreign key, column]. */
    private const array SORT_RELATIONS = [
        'product_code' => ['products', 'stock_movements.product_id', 'code'],
        'product_name' => ['products', 'stock_movements.product_id', 'name'],
    ];

    public function domain(): string
    {
        return 'stock-movements';
    }

    /**
     * @return class-string<StockMovement>
     */
    public function modelClass(): string
    {
        return StockMovement::class;
    }

    public function authorizeViewAny(User $actor): bool
    {
        return app(StockPolicy::class)->viewAny($actor);
    }

    /**
     * @return Builder<StockMovement>
     */
    public function baseQuery(): Builder
    {
        return StockMovement::query()->with(StockMovementService::MOVEMENT_RELATIONS);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $column = static fn (string $id, string $type, bool $sortable = false, ?string $filter = null, ?array $options = null, bool $searchable = false): array => Catalog::column($id, $type, sortable: $sortable, filter: $filter, options: $options, searchable: $searchable, prefix: self::LABELS);

        return [
            $column('moved_on', 'date', true, 'date'),
            $column('type', 'badge', true, 'set', StockMovementType::values()),
            $column('product_code', 'text', true),
            $column('product_name', 'text', true, searchable: true),
            $column('quantity', 'number'),
            $column('from_site', 'text'),
            $column('from_user', 'text'),
            $column('to_site', 'text'),
            $column('to_user', 'text'),
            $column('note', 'text'),
            $column('created_by_name', 'text'),
            $column('created_at', 'datetime', true),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return Catalog::filtersFor($this->columns());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'moved_on', 'direction' => 'desc']];
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
        /** @var StockMovement $row */
        return [
            'id' => $row->id,
            'moved_on' => $row->moved_on->format('Y-m-d'),
            'type' => $row->type->value,
            'product_code' => $row->product->code,
            'product_name' => $row->product->name,
            'quantity' => $row->quantity,
            'from_site' => $row->fromOperationalSite === null ? null : OperationalSiteLabel::displayName($row->fromOperationalSite),
            'from_user' => $row->fromUser?->name,
            'to_site' => $row->toOperationalSite === null ? null : OperationalSiteLabel::displayName($row->toOperationalSite),
            'to_user' => $row->toUser?->name,
            'note' => $row->note,
            'created_by_name' => $row->creator->name,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === 'type'
            ? array_map(static fn ($meta): array => $meta->toArray(), StockMovementType::options())
            : null;
    }

    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'type' ? 'stock_movement_type' : null;
    }

    /**
     * @param  Builder<StockMovement>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if (! isset(self::SORT_RELATIONS[$columnId])) {
            return false;
        }

        [$table, $foreignKey, $column] = self::SORT_RELATIONS[$columnId];
        Derived::orderByRelated($query, $table, $foreignKey, $column, $direction);

        return true;
    }

    /**
     * @param  Builder<StockMovement>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        if ($columnId !== 'product_name') {
            return false;
        }

        $query->orWhereHas('product', static fn (Builder $product) => $product->where('name', 'like', $pattern));

        return true;
    }
}
