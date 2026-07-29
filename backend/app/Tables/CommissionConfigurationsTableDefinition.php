<?php

namespace App\Tables;

use App\Authorization\AuthorizationRegistry;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\CommissionConfigurationService;
use App\Tables\CommissionConfigurations\CommissionConfigurationAdvancedFilterCatalog;
use App\Tables\CommissionConfigurations\CommissionConfigurationColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CommissionConfigurationsTableDefinition extends AbstractTableDefinition
{
    /** @var array<string, string> */
    private const array COLUMN_FIELDS = [
        'name' => 'name',
        'recipient_role' => 'recipient_role',
        'application_scope' => 'application_scope',
        'category' => 'product_category_id',
        'product' => 'product_id',
        'commission_type' => 'commission_type',
        'value' => 'value',
        'priority' => 'priority',
        'status' => 'status',
    ];

    public function __construct(
        private readonly CommissionConfigurationService $service,
        private readonly AuthorizationRegistry $authorization,
    ) {}

    public function domain(): string
    {
        return 'commission-configurations';
    }

    public function modelClass(): string
    {
        return CommissionConfiguration::class;
    }

    public function baseQuery(): Builder
    {
        return CommissionConfiguration::query()->with(['productCategory', 'product']);
    }

    public function columns(): array
    {
        return CommissionConfigurationColumnCatalog::columns();
    }

    public function filters(): array
    {
        return CommissionConfigurationColumnCatalog::filters();
    }

    public function actions(): array
    {
        return CommissionConfigurationColumnCatalog::actions();
    }

    public function advancedFilters(): array
    {
        return CommissionConfigurationAdvancedFilterCatalog::advancedFilters();
    }

    public function defaultSort(): array
    {
        return [['columnId' => 'priority', 'direction' => 'desc']];
    }

    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    public function resolveConfig(User $actor): array
    {
        $config = parent::resolveConfig($actor);
        $visibleColumns = $this->visibleColumnIds($actor);

        $config['columns'] = array_values(array_filter(
            $config['columns'],
            static fn (array $column): bool => in_array($column['id'], $visibleColumns, true),
        ));
        $config['filters'] = array_values(array_filter(
            $config['filters'],
            static fn (array $filter): bool => in_array($filter['columnId'], $visibleColumns, true),
        ));
        $config['searchable'] = array_values(array_intersect($config['searchable'], $visibleColumns));

        return $config;
    }

    protected function badgesFor(string $columnId, User $actor): ?array
    {
        $enum = match ($columnId) {
            'recipient_role' => CommissionRecipientRole::class,
            'application_scope' => CommissionApplicationScope::class,
            'commission_type' => CommissionType::class,
            'status' => CommissionConfigurationStatus::class,
            default => null,
        };

        if ($enum === null) {
            return null;
        }

        return array_map(static fn ($meta): array => $meta->toArray(), $enum::options());
    }

    public function mapRow(User $actor, Model $row): array
    {
        /** @var CommissionConfiguration $row */
        $payload = [
            'id' => $row->id,
            'name' => $row->name,
            'recipient_role' => $row->recipient_role->value,
            'application_scope' => $row->application_scope->value,
            'category' => $row->productCategory?->name,
            'product' => $row->product?->name,
            'commission_type' => $row->commission_type->value,
            'value' => (float) $row->value,
            'priority' => $row->priority,
            'status' => $row->status->value,
            'updated_at' => $row->updated_at,
        ];

        foreach (self::COLUMN_FIELDS as $column => $field) {
            if (! $this->fieldVisible($actor, $row, $field)) {
                unset($payload[$column]);
            }
        }

        return $payload;
    }

    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        foreach (['view' => 'view', 'update' => 'edit', 'delete' => 'delete', 'viewActivity' => 'activity'] as $ability => $action) {
            if (Gate::forUser($actor)->allows($ability, $row)) {
                $allowed[] = $action;
            }
        }

        return $allowed;
    }

    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $relation = match ($columnId) {
            'category' => 'productCategory',
            'product' => 'product',
            default => null,
        };

        if ($relation === null) {
            return false;
        }

        $values = array_values(array_filter((array) ($filter['values'] ?? []), 'is_string'));
        if ($values !== []) {
            $query->whereHas($relation, fn (Builder $related) => $related->whereIn('name', array_slice($values, 0, 200)));
        }

        return true;
    }

    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $subquery = match ($columnId) {
            'category' => ProductCategory::query()
                ->select('name')
                ->whereColumn('product_categories.id', 'commission_configurations.product_category_id')
                ->limit(1),
            'product' => Product::query()
                ->select('name')
                ->whereColumn('products.id', 'commission_configurations.product_id')
                ->limit(1),
            default => null,
        };

        if ($subquery === null) {
            return false;
        }

        $query->orderBy($subquery, $direction);

        return true;
    }

    public function distinctValues(
        User $actor,
        string $columnId,
        array $columnConfig,
        ?string $search,
        Builder $query,
        int $limit,
    ): ?array {
        [$table, $foreignKey] = match ($columnId) {
            'category' => ['product_categories', 'product_category_id'],
            'product' => ['products', 'product_id'],
            default => [null, null],
        };

        if ($table === null || $foreignKey === null) {
            return null;
        }

        $permissionField = self::COLUMN_FIELDS[$columnId] ?? null;
        if ($permissionField !== null && ! $this->fieldVisible($actor, new CommissionConfiguration, $permissionField)) {
            return [];
        }

        return DB::table($table)
            ->whereIn('id', (clone $query)->whereNotNull($foreignKey)->select($foreignKey))
            ->when($search !== null && $search !== '', fn ($builder) => $builder->where('name', 'like', '%'.$this->escapeLike($search).'%'))
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    public function deleteModel(Model $model): void
    {
        /** @var CommissionConfiguration $model */
        $this->service->delete($model);
    }

    /** @return array<int, string> */
    private function visibleColumnIds(User $actor): array
    {
        $model = new CommissionConfiguration;

        return collect($this->columns())
            ->pluck('id')
            ->filter(fn (string $column): bool => ! isset(self::COLUMN_FIELDS[$column])
                || $this->fieldVisible($actor, $model, self::COLUMN_FIELDS[$column]))
            ->prepend('id')
            ->push('updated_at')
            ->unique()
            ->values()
            ->all();
    }

    private function fieldVisible(
        User $actor,
        CommissionConfiguration $configuration,
        string $field,
    ): bool {
        return $this->authorization
            ->resolve('commission-configurations')
            ->fieldPermissions($actor, $configuration)[$field]
            ->visible;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
