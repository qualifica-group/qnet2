<?php

namespace App\Tables\Stock;

use App\Enums\StockHolder;
use App\Models\StockBalance;
use App\Models\User;
use App\Policies\StockPolicy;
use App\Support\OperationalSiteLabel;
use App\Tables\AbstractTableDefinition;
use App\Tables\PurchaseRequests\PurchaseRequestColumnCatalog as Catalog;
use App\Tables\PurchaseRequests\PurchaseRequestDerivedQuery as Derived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Table definition for the `stock-balances` domain (spec 0307, D-19): the
 * stock rows (product, site, optional user) with a quantity above zero. The
 * row actions open the movement form (manage) or the history; no delete: the
 * rows change only through the movements.
 */
class StockBalancesTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'stock.balances';

    /** Text filters on a related column: id => [relation, column]. */
    private const array TEXT_RELATIONS = [
        'product_code' => ['product', 'code'],
        'product_name' => ['product', 'name'],
        'user_name' => ['user', 'name'],
    ];

    /** Sortable related columns: id => [related table, foreign key, column]. */
    private const array SORT_RELATIONS = [
        'product_code' => ['products', 'stock_balances.product_id', 'code'],
        'product_name' => ['products', 'stock_balances.product_id', 'name'],
    ];

    public function domain(): string
    {
        return 'stock-balances';
    }

    /**
     * @return class-string<StockBalance>
     */
    public function modelClass(): string
    {
        return StockBalance::class;
    }

    public function authorizeViewAny(User $actor): bool
    {
        return app(StockPolicy::class)->viewAny($actor);
    }

    /**
     * @return Builder<StockBalance>
     */
    public function baseQuery(): Builder
    {
        return StockBalance::query()
            ->with(['product.category', 'product.unitOfMeasure', 'operationalSite.addresses.city', 'user:id,name'])
            ->where('quantity', '>', 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $column = static fn (string $id, string $type, bool $sortable = false, ?string $filter = null, ?array $options = null, bool $searchable = false): array => Catalog::column($id, $type, sortable: $sortable, filter: $filter, options: $options, searchable: $searchable, prefix: self::LABELS);

        return [
            $column('product_code', 'text', true, 'text'),
            $column('product_name', 'text', true, 'text', searchable: true),
            $column('product_category', 'text'),
            $column('operational_site', 'text', filter: 'set'),
            $column('user_name', 'text', filter: 'text'),
            $column('holder', 'badge', filter: 'set', options: StockHolder::values()),
            $column('quantity', 'number', true),
            $column('unit_of_measure', 'text'),
            $column('last_moved_on', 'date', true, 'date'),
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
        $action = static fn (string $key, string $icon, string $permission): array => [
            'key' => $key,
            'label' => "stock.actions.{$key}",
            'icon' => $icon,
            'type' => 'action',
            'confirm' => false,
            'permission' => "stock.{$permission}",
        ];

        return [
            $action('assign', 'user-plus', 'manage'),
            $action('return', 'undo-2', 'manage'),
            $action('transfer', 'arrow-left-right', 'manage'),
            $action('unload', 'package-minus', 'manage'),
            $action('adjust', 'scale', 'manage'),
            $action('history', 'history', 'viewAny'),
        ];
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'product_name', 'direction' => 'asc']];
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
        /** @var StockBalance $row */
        return [
            'id' => $row->id,
            'product_id' => $row->product_id,
            'operational_site_id' => $row->operational_site_id,
            'user_id' => $row->user_id,
            'product_code' => $row->product->code,
            'product_name' => $row->product->name,
            'product_category' => $row->product->category?->name,
            'operational_site' => OperationalSiteLabel::displayName($row->operationalSite),
            'user_name' => $row->user?->name,
            'holder' => $row->holder()->value,
            'quantity' => $row->quantity,
            'unit_of_measure' => $row->product->unitOfMeasure->name,
            'last_moved_on' => $row->last_moved_on->format('Y-m-d'),
            'abilities' => ['manage' => app(StockPolicy::class)->manage($actor)],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var StockBalance $row */
        $policy = app(StockPolicy::class);
        $allowed = $policy->viewAny($actor) ? ['history'] : [];

        if (! $policy->manage($actor)) {
            return $allowed;
        }

        return [
            $row->user_id === null ? 'assign' : 'return',
            'transfer',
            'unload',
            'adjust',
            ...$allowed,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === 'holder'
            ? array_map(static fn ($meta): array => $meta->toArray(), StockHolder::options())
            : null;
    }

    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'holder' ? 'stock_holder' : null;
    }

    /**
     * @param  Builder<StockBalance>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (isset(self::TEXT_RELATIONS[$columnId])) {
            Derived::applyText($query, self::TEXT_RELATIONS[$columnId], $filter);

            return true;
        }

        if ($columnId === 'operational_site') {
            StockSiteColumn::applySet($query, 'operational_site_id', $filter);

            return true;
        }

        if ($columnId === 'holder') {
            $this->applyHolder($query, $filter);

            return true;
        }

        return false;
    }

    /**
     * @param  Builder<StockBalance>  $query
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
     * @param  Builder<StockBalance>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === 'operational_site') {
            return StockSiteColumn::distinct($query, 'operational_site_id', $search, $limit);
        }

        return isset(self::TEXT_RELATIONS[$columnId])
            ? Derived::distinctRelated($query, self::TEXT_RELATIONS[$columnId], $search, $limit)
            : null;
    }

    /**
     * @param  Builder<StockBalance>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        if ($columnId !== 'product_name') {
            return false;
        }

        $query->orWhereHas('product', static fn (Builder $product) => $product->where('name', 'like', $pattern));

        return true;
    }

    /**
     * @param  Builder<StockBalance>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyHolder(Builder $query, array $filter): void
    {
        $values = is_array($filter['values'] ?? null) ? $filter['values'] : [];
        $free = in_array(StockHolder::Free->value, $values, true);
        $assigned = in_array(StockHolder::Assigned->value, $values, true);

        if ($free !== $assigned) {
            $free ? $query->whereNull('user_id') : $query->whereNotNull('user_id');
        }
    }
}
