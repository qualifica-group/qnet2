<?php

namespace App\Tables;

use App\Models\PayrollCost;
use App\Models\User;
use App\Services\Table\FilterApplier;
use App\Tables\PayrollCosts\PayrollCostColumnCatalog as Catalog;
use App\Tables\PayrollCosts\PayrollCostComputedColumns;
use App\Tables\PayrollCosts\PayrollCostSql as Sql;
use App\Tables\Support\SetExpressionQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Table definition for the `payroll-costs` domain (spec 0430 D-11): the read-only list of the imported personnel
 * costs per person, company and month, with the share of each cost charged to the work orders.
 */
class PayrollCostsTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'payroll-costs';
    }

    /**
     * @return class-string<PayrollCost>
     */
    public function modelClass(): string
    {
        return PayrollCost::class;
    }

    /**
     * @return Builder<PayrollCost>
     */
    public function baseQuery(): Builder
    {
        return Sql::withJoins(PayrollCost::query())
            ->with('user.personalData')
            ->afterQuery(static function (Collection $page): void {
                PayrollCostComputedColumns::stampMinutes($page);
            });
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
        return [];
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'period', 'direction' => 'desc'],
            ['columnId' => 'id', 'direction' => 'desc'],
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
        /** @var PayrollCost $row */
        return [
            'id' => $row->id,
            'period' => $row->period,
            'company' => $row->getAttribute('company_name'),
            'user' => $this->personName($row),
            'tax_code' => $row->tax_code,
            'salary_cost' => $row->salary_cost,
            'contribution_cost' => $row->contribution_cost,
            'insurance_cost' => $row->insurance_cost,
            'total_cost' => $row->total_cost,
            'ordinary_hours' => $row->ordinary_hours,
            'hired_on' => $row->hired_on?->toDateString(),
            'terminated_on' => $row->terminated_on?->toDateString(),
            ...PayrollCostComputedColumns::forRow($row),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return [];
    }

    public function authorizeUpdate(User $actor, Model $row): bool
    {
        return false;
    }

    public function authorizeDelete(User $actor, Model $row): bool
    {
        return false;
    }

    /**
     * @param  Builder<PayrollCost>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (! isset(Sql::PLAIN_COLUMNS[$columnId])) {
            return false;
        }

        if (in_array($columnId, Sql::SET_COLUMNS, true)) {
            SetExpressionQuery::apply($query, Sql::PLAIN_COLUMNS[$columnId], $filter);

            return true;
        }

        app(FilterApplier::class)->apply($query, Sql::PLAIN_COLUMNS[$columnId], $columnConfig, $filter);

        return true;
    }

    /**
     * @param  Builder<PayrollCost>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if (! isset(Sql::PLAIN_COLUMNS[$columnId])) {
            return false;
        }

        $query->orderBy(Sql::PLAIN_COLUMNS[$columnId], $direction === 'desc' ? 'desc' : 'asc');

        return true;
    }

    /**
     * @param  Builder<PayrollCost>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string|null>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if (! in_array($columnId, Sql::SET_COLUMNS, true)) {
            return null;
        }

        return SetExpressionQuery::distinct($query, Sql::PLAIN_COLUMNS[$columnId], $search, $limit);
    }

    /** "Last name first name" from the personal data card, the user name when the card has none. */
    private function personName(PayrollCost $row): string
    {
        $card = $row->user->personalData;
        $name = trim(implode(' ', array_filter([$card?->last_name, $card?->first_name])));

        return $name !== '' ? $name : (string) $row->getAttribute('user_name');
    }
}
