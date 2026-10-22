<?php

namespace App\Tables;

use App\Enums\CompanyClosureKind;
use App\Models\CompanyClosure;
use App\Models\User;
use App\Services\CompanyClosures\CompanyClosureService;
use App\Support\OperationalSiteLabel;
use App\Tables\Support\CatalogActions;
use App\Tables\Support\CatalogColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `company-closures` domain (spec 0304 D-13).
 */
class CompanyClosuresTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'companyClosures.columns';

    public function __construct(private readonly CompanyClosureService $service) {}

    public function domain(): string
    {
        return 'company-closures';
    }

    /**
     * @return class-string<CompanyClosure>
     */
    public function modelClass(): string
    {
        return CompanyClosure::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition derives CompanyClosurePolicy::viewAny from modelClass().

    /**
     * @return Builder<CompanyClosure>
     */
    public function baseQuery(): Builder
    {
        return CompanyClosure::query()
            ->with(CompanyClosureService::RELATIONS)
            ->withCount('absenceRequests');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            CatalogColumns::name(self::LABELS),
            $this->column('starts_on', 'date', sortable: true, filter: 'date'),
            $this->column('ends_on', 'date', sortable: true, filter: 'date'),
            $this->column('kind', 'badge', sortable: true, filter: 'set', options: CompanyClosureKind::values()),
            $this->column('absence_type_name', 'text'),
            $this->column('sites', 'text'),
            $this->column('repeats_yearly', 'boolean', sortable: true),
            $this->column('generated_requests_count', 'number'),
        ];
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private function column(string $id, string $type, bool $sortable = false, ?string $filter = null, ?array $options = null): array
    {
        $column = [
            'id' => $id,
            'label' => self::LABELS.".{$id}",
            'type' => $type,
            'visible' => true,
            'sortable' => $sortable,
            'filterable' => $filter !== null,
        ];

        if ($filter !== null) {
            $column['filterType'] = $filter;
        }

        return $options === null ? $column : [...$column, 'options' => $options];
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
        return CatalogActions::for('company-closures');
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === 'kind'
            ? array_map(static fn ($meta): array => $meta->toArray(), CompanyClosureKind::options())
            : null;
    }

    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'kind' ? 'company_closure_kind' : null;
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'starts_on', 'direction' => 'desc']];
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
        /** @var CompanyClosure $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'starts_on' => $row->starts_on->format('Y-m-d'),
            'ends_on' => $row->ends_on->format('Y-m-d'),
            'kind' => $row->kind->value,
            'absence_type_name' => $row->absenceType?->name,
            'sites' => $row->applies_to_all_sites ? __('company_closures.all_sites') : $row->operationalSites->map(OperationalSiteLabel::displayName(...))->implode(', '),
            'repeats_yearly' => $row->repeats_yearly,
            'generated_requests_count' => (int) $row->absence_requests_count,
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
     * Delegate to the service so the generic bulk-delete endpoint applies the SAME
     * guard (409 once started) and clean-up as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var CompanyClosure $model */
        $this->service->delete($model);
    }
}
