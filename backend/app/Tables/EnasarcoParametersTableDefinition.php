<?php

namespace App\Tables;

use App\Models\EnasarcoParameter;
use App\Models\User;
use App\Services\EnasarcoParameterService;
use App\Tables\EnasarcoParameters\EnasarcoParameterColumnCatalog as Catalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `enasarco-parameters` domain (spec 0213): the yearly
 * ENASARCO rates and ceilings, plain columns served by the generic engine.
 * A delete goes through the service so the "year used by an issued
 * settlement" guard also holds for the bulk delete.
 */
class EnasarcoParametersTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly EnasarcoParameterService $service) {}

    public function domain(): string
    {
        return 'enasarco-parameters';
    }

    /**
     * @return class-string<EnasarcoParameter>
     */
    public function modelClass(): string
    {
        return EnasarcoParameter::class;
    }

    /**
     * @return Builder<EnasarcoParameter>
     */
    public function baseQuery(): Builder
    {
        return EnasarcoParameter::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return Catalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return Catalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return Catalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'year', 'direction' => 'desc']];
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
        /** @var EnasarcoParameter $row */
        return [
            'id' => $row->id,
            'year' => $row->year,
            'total_rate' => $row->total_rate,
            'agent_share_percent' => $row->agent_share_percent,
            'single_firm_ceiling' => $row->single_firm_ceiling,
            'multi_firm_ceiling' => $row->multi_firm_ceiling,
            'updated_at' => $row->updated_at,
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

        foreach (['view' => 'view', 'delete' => 'delete', 'viewActivity' => 'activity'] as $ability => $action) {
            if (Gate::forUser($actor)->allows($ability, $row)) {
                $allowed[] = $action;
            }
        }

        return $allowed;
    }

    public function deleteModel(Model $model): void
    {
        /** @var EnasarcoParameter $model */
        $this->service->delete($model);
    }
}
