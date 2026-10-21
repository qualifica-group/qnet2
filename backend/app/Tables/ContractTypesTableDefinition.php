<?php

namespace App\Tables;

use App\Models\ContractType;
use App\Models\User;
use App\Services\ContractTypeService;
use App\Tables\Support\CatalogActions;
use App\Tables\Support\CatalogColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `contract-types` domain (spec 0290 D-1).
 */
class ContractTypesTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'contractTypes.columns';

    public function __construct(private readonly ContractTypeService $service) {}

    public function domain(): string
    {
        return 'contract-types';
    }

    /**
     * @return class-string<ContractType>
     */
    public function modelClass(): string
    {
        return ContractType::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition derives ContractTypePolicy::viewAny from modelClass().

    /**
     * @return Builder<ContractType>
     */
    public function baseQuery(): Builder
    {
        return ContractType::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            CatalogColumns::name(self::LABELS),
            [
                'id' => 'is_active',
                'label' => self::LABELS.'.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'sort_order',
                'label' => self::LABELS.'.sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return [['columnId' => 'name', 'type' => 'text']];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return CatalogActions::for('contract-types');
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'sort_order', 'direction' => 'asc']];
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
        /** @var ContractType $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'is_active' => $row->is_active,
            'sort_order' => $row->sort_order,
            'abilities' => [
                'update' => Gate::forUser($actor)->allows('update', $row),
                'delete' => Gate::forUser($actor)->allows('delete', $row),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        foreach (['view', 'delete'] as $ability) {
            if (Gate::forUser($actor)->allows($ability, $row)) {
                $allowed[] = $ability;
            }
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to ContractTypeService::delete() so the generic bulk-delete
     * endpoint respects the SAME guard (409) as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var ContractType $model */
        $this->service->delete($model);
    }
}
