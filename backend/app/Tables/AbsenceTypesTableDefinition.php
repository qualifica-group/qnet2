<?php

namespace App\Tables;

use App\Models\AbsenceType;
use App\Models\User;
use App\Services\AbsenceTypeService;
use App\Tables\Support\CatalogActions;
use App\Tables\Support\CatalogColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `absence-types` domain (spec 0278 D-5).
 */
class AbsenceTypesTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'absenceTypes.columns';

    public function __construct(private readonly AbsenceTypeService $service) {}

    public function domain(): string
    {
        return 'absence-types';
    }

    /**
     * @return class-string<AbsenceType>
     */
    public function modelClass(): string
    {
        return AbsenceType::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition derives AbsenceTypePolicy::viewAny from modelClass().

    /**
     * @return Builder<AbsenceType>
     */
    public function baseQuery(): Builder
    {
        return AbsenceType::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            CatalogColumns::name(self::LABELS),
            $this->booleanColumn('deducts_balance'),
            $this->booleanColumn('requires_approval'),
            $this->booleanColumn('is_active'),
            $this->booleanColumn('allows_full_day'),
            $this->booleanColumn('allows_half_day'),
            $this->booleanColumn('allows_hourly'),
            $this->numberColumn('min_notice_days'),
            [
                'id' => 'color',
                'label' => self::LABELS.'.color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            $this->numberColumn('default_annual_days'),
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
     * @return array<string, mixed>
     */
    private function numberColumn(string $id): array
    {
        return [
            'id' => $id,
            'label' => self::LABELS.".{$id}",
            'type' => 'number',
            'visible' => true,
            'sortable' => true,
            'filterable' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function booleanColumn(string $id): array
    {
        return [
            'id' => $id,
            'label' => self::LABELS.".{$id}",
            'type' => 'boolean',
            'visible' => true,
            'sortable' => true,
            'filterable' => false,
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
        return CatalogActions::for('absence-types');
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
        /** @var AbsenceType $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'deducts_balance' => $row->deducts_balance,
            'requires_approval' => $row->requires_approval,
            'is_active' => $row->is_active,
            'sort_order' => $row->sort_order,
            'allows_full_day' => $row->allows_full_day,
            'allows_half_day' => $row->allows_half_day,
            'allows_hourly' => $row->allows_hourly,
            'min_notice_days' => $row->min_notice_days,
            'color' => $row->color,
            'default_annual_days' => $row->default_annual_days === null ? null : (float) $row->default_annual_days,
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
     * Delegate to AbsenceTypeService::delete() so the generic bulk-delete
     * endpoint respects the SAME guard (409) as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var AbsenceType $model */
        $this->service->delete($model);
    }
}
