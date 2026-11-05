<?php

namespace App\Tables\TreasuryMovements;

use App\Models\TreasuryMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Derived relation columns of the `treasury-movements` grid (spec 0355): sort,
 * set filter and distinct values resolved through bound subqueries on an
 * allow-list of columns (never request input in SQL). Each entry maps the grid
 * column to the foreign key on `treasury_movements` and the label column of the
 * related table; `company` is the company of the movement's account.
 */
final class TreasuryMovementRelationColumns
{
    private const array MAP = [
        'account' => ['fk' => 'financial_account_id', 'table' => 'financial_accounts', 'label' => 'name'],
        'counterpart_account' => ['fk' => 'counterpart_account_id', 'table' => 'financial_accounts', 'label' => 'name'],
        'registry' => ['fk' => 'registry_id', 'table' => 'registries', 'label' => 'name'],
        'work_order' => ['fk' => 'work_order_id', 'table' => 'work_orders', 'label' => 'code'],
        'created_by' => ['fk' => 'created_by', 'table' => 'users', 'label' => 'name'],
        'company' => ['fk' => 'financial_account_id', 'table' => 'companies', 'label' => 'denomination'],
    ];

    /** Caps the WHERE IN cardinality of a set filter. */
    private const int MAX_FILTER_VALUES = 200;

    public static function handles(string $columnId): bool
    {
        return isset(self::MAP[$columnId]);
    }

    /**
     * @param  Builder<TreasuryMovement>  $query
     */
    public static function sort(Builder $query, string $columnId, string $direction): void
    {
        $query->orderBy(self::labelOf($columnId)->limit(1), $direction === 'desc' ? 'desc' : 'asc');
    }

    /**
     * @param  Builder<TreasuryMovement>  $query
     * @param  array<string, mixed>  $filter
     */
    public static function filter(Builder $query, string $columnId, array $filter): void
    {
        $values = is_array($filter['values'] ?? null) ? $filter['values'] : [];
        $names = array_slice(array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== '')), 0, self::MAX_FILTER_VALUES);
        $blank = in_array(null, $values, true);

        if ($names === [] && ! $blank) {
            return;
        }

        $fk = self::MAP[$columnId]['fk'];
        $query->where(function (Builder $group) use ($columnId, $fk, $names, $blank): void {
            if ($names !== []) {
                $group->whereIn($fk, self::idsByLabel($columnId, $names));
            }

            if ($blank) {
                $group->orWhere(fn (Builder $blankGroup) => self::whereBlank($blankGroup, $columnId));
            }
        });
    }

    /**
     * Distinct labels used by the scoped rows, with the blank entry first when some row has none.
     *
     * @param  Builder<TreasuryMovement>  $query
     * @return array<int, string|null>
     */
    public static function distinct(string $columnId, Builder $query, ?string $search, int $limit): array
    {
        $map = self::MAP[$columnId];
        $used = (clone $query)->reorder()->whereNotNull($map['fk'])->select($map['fk']);
        $table = $map['table'];

        $labels = DB::table($table)
            ->whereIn('id', $columnId === 'company' ? self::companyIdsOf($used) : $used)
            ->when($search !== null && $search !== '', fn (QueryBuilder $builder) => $builder->where($map['label'], 'like', '%'.$search.'%'))
            ->distinct()->orderBy($map['label'])->limit($limit)
            ->pluck($map['label'])
            ->map(static fn (mixed $label): string => (string) $label)
            ->all();

        $hasBlank = ($search === null || $search === '')
            && self::whereBlank((clone $query)->reorder(), $columnId)->exists();

        return $hasBlank ? [null, ...$labels] : $labels;
    }

    private static function labelOf(string $columnId): QueryBuilder
    {
        $map = self::MAP[$columnId];

        if ($columnId === 'company') {
            return DB::table('financial_accounts')
                ->join('companies', 'companies.id', '=', 'financial_accounts.company_id')
                ->whereColumn('financial_accounts.id', 'treasury_movements.financial_account_id')
                ->select('companies.denomination');
        }

        return DB::table($map['table'])
            ->whereColumn($map['table'].'.id', 'treasury_movements.'.$map['fk'])
            ->select($map['table'].'.'.$map['label']);
    }

    /**
     * @param  array<int, string>  $names
     */
    private static function idsByLabel(string $columnId, array $names): QueryBuilder
    {
        $map = self::MAP[$columnId];

        if ($columnId === 'company') {
            return DB::table('financial_accounts')
                ->join('companies', 'companies.id', '=', 'financial_accounts.company_id')
                ->whereIn('companies.denomination', $names)
                ->select('financial_accounts.id');
        }

        return DB::table($map['table'])->whereIn($map['label'], $names)->select('id');
    }

    /**
     * Rows whose cell is empty: no related row, or (company) an account without company.
     *
     * @param  Builder<TreasuryMovement>  $query
     * @return Builder<TreasuryMovement>
     */
    private static function whereBlank(Builder $query, string $columnId): Builder
    {
        if ($columnId === 'company') {
            return $query->whereIn('financial_account_id', DB::table('financial_accounts')->whereNull('company_id')->select('id'));
        }

        return $query->whereNull(self::MAP[$columnId]['fk']);
    }

    private static function companyIdsOf(Builder|QueryBuilder $accountIds): QueryBuilder
    {
        return DB::table('financial_accounts')->whereIn('id', $accountIds)->whereNotNull('company_id')->select('company_id');
    }
}
