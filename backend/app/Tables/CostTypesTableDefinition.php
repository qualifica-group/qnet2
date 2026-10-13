<?php

namespace App\Tables;

use App\DataObjects\Enums\EnumMeta;
use App\Enums\CostTypeNature;
use App\Models\CostType;
use App\Models\User;
use App\Services\CostTypeService;
use App\Tables\CostTypes\CostTypeColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `cost-types` domain (spec 0210). Every column is a
 * real DB column except `lines_count` (`withCount`); `used_by_invoices` (`withExists`) only hides the delete action.
 */
class CostTypesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly CostTypeService $service) {}

    public function domain(): string
    {
        return 'cost-types';
    }

    /**
     * @return class-string<CostType>
     */
    public function modelClass(): string
    {
        return CostType::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition derives CostTypePolicy::viewAny from modelClass().

    /**
     * @return Builder<CostType>
     */
    public function baseQuery(): Builder
    {
        return CostType::query()
            ->withCount(['purchaseOrderLines as lines_count'])
            ->withExists(['purchaseInvoices as used_by_invoices']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return CostTypeColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return CostTypeColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return CostTypeColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'sort_order', 'direction' => 'asc'],
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
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var CostType $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'nature' => $row->nature->value,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
            'lines_count' => (int) $row->lines_count,
            'abilities' => [
                'update' => Gate::forUser($actor)->allows('update', $row),
                'delete' => Gate::forUser($actor)->allows('delete', $row),
            ],
        ];
    }

    /**
     * Badge metadata of the `nature` column, driven by CostTypeNature.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === 'nature'
            ? array_map(static fn (EnumMeta $meta): array => $meta->toArray(), CostTypeNature::options())
            : null;
    }

    /**
     * Frontend `enums.cost_type_nature` key (config/config.php form_enums): the
     * cells and the Set Filter checklist read the same localized labels.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'nature' ? 'cost_type_nature' : null;
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var CostType $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        $inUse = $row->lines_count > 0 || $row->used_by_invoices;

        if (! $inUse && Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to CostTypeService::delete() so the generic bulk-delete endpoint
     * respects the SAME guard as the single DELETE /cost-types/{costType}.
     */
    public function deleteModel(Model $model): void
    {
        /** @var CostType $model */
        $this->service->delete($model);
    }
}
