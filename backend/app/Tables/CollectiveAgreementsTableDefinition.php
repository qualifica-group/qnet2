<?php

namespace App\Tables;

use App\Models\CollectiveAgreement;
use App\Models\User;
use App\Services\CollectiveAgreementService;
use App\Tables\Support\CatalogActions;
use App\Tables\Support\CatalogColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `collective-agreements` domain (spec 0272 D-3).
 * `levels_count` is a `withCount` aggregate over the agreement levels.
 */
class CollectiveAgreementsTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'collectiveAgreements.columns';

    public function __construct(private readonly CollectiveAgreementService $service) {}

    public function domain(): string
    {
        return 'collective-agreements';
    }

    /**
     * @return class-string<CollectiveAgreement>
     */
    public function modelClass(): string
    {
        return CollectiveAgreement::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition derives CollectiveAgreementPolicy::viewAny from modelClass().

    /**
     * @return Builder<CollectiveAgreement>
     */
    public function baseQuery(): Builder
    {
        return CollectiveAgreement::query()->withCount('levels');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            CatalogColumns::name(self::LABELS),
            [
                'id' => 'code',
                'label' => self::LABELS.'.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            CatalogColumns::count('levels_count', self::LABELS),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'code', 'type' => 'text'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return CatalogActions::for('collective-agreements');
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'name', 'direction' => 'asc']];
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
        /** @var CollectiveAgreement $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'code' => $row->code,
            'levels_count' => (int) $row->levels_count,
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
     * Delegate to CollectiveAgreementService::delete() so the generic bulk-delete
     * endpoint respects the SAME guard (409) as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var CollectiveAgreement $model */
        $this->service->delete($model);
    }
}
