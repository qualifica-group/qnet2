<?php

namespace App\Tables;

use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\FinancialAccountService;
use App\Tables\FinancialAccounts\FinancialAccountColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `financial-accounts` domain (spec 0189). Every
 * column is a real DB column handled by the generic engine, except the derived
 * `company` (the owning company's denomination, filtered via whereHas). The
 * row payload never carries card data, and neither does the export.
 */
class FinancialAccountsTableDefinition extends AbstractTableDefinition
{
    /** Caps the WHERE IN cardinality of the `company` set filter. */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(private readonly FinancialAccountService $service) {}

    public function domain(): string
    {
        return 'financial-accounts';
    }

    /**
     * @return class-string<FinancialAccount>
     */
    public function modelClass(): string
    {
        return FinancialAccount::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default derives FinancialAccountPolicy::viewAny from modelClass().

    /**
     * @return Builder<FinancialAccount>
     */
    public function baseQuery(): Builder
    {
        return FinancialAccount::query()->with('company:id,denomination');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return FinancialAccountColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return FinancialAccountColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return FinancialAccountColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
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
        /** @var FinancialAccount $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'iban' => $row->iban,
            'type' => $row->type->value,
            'notes' => $row->notes,
            'company' => $row->company === null ? null : ['id' => $row->company->id, 'name' => $row->company->denomination],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var FinancialAccount $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to FinancialAccountService::delete() so the generic bulk-delete
     * endpoint respects the same guard (D-10) as the single DELETE endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var FinancialAccount $model */
        $this->service->delete($model);
    }

    /**
     * The derived `company` set filter (no real DB column).
     *
     * @param  Builder<FinancialAccount>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId !== 'company') {
            return false;
        }

        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($names === [] && ! $matchesBlank) {
            return true;
        }

        $query->where(static function (Builder $group) use ($names, $matchesBlank): void {
            if ($names !== []) {
                $group->whereHas('company', static function (Builder $relatedQuery) use ($names): void {
                    $relatedQuery->whereIn('denomination', $names);
                });
            }

            // The blank entry ("(Vuoti)"): the accounts tied to no company.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('company');
            }
        });

        return true;
    }

    /**
     * Excel-like distinct values for the derived `company` column; the real
     * columns fall through to the generic engine (null).
     *
     * @param  Builder<FinancialAccount>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId !== 'company') {
            return null;
        }

        $companyIds = (clone $query)->whereNotNull('company_id')->select('company_id');

        $values = DB::table('companies')
            ->whereIn('id', $companyIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('denomination', 'like', '%'.$search.'%');
            })
            ->distinct()
            ->orderBy('denomination')
            ->limit($limit)
            ->pluck('denomination')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave('company')
            ->exists());
    }
}
