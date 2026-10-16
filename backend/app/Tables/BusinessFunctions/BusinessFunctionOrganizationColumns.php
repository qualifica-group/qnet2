<?php

namespace App\Tables\BusinessFunctions;

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the `business-functions` domain's `companies` (belongsToMany, aggregated, not sortable) derived column
 * (spec 0271). It matches by the company's display name, via a column
 * allow-list (`denomination`) so no raw input reaches the query.
 */
final class BusinessFunctionOrganizationColumns
{
    use HandlesBlankSetFilter;

    public const string COMPANIES = 'companies';

    private const int MAX_FILTER_VALUES = 200;

    /** Allow-list: the only column a derived filter/distinct list may touch. */
    private const string NAME_COLUMN = 'denomination';

    /**
     * Derived set filter, matched by name; the blank entry selects the rows
     * with no related record.
     *
     * @param  Builder<BusinessFunction>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
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

        $nameColumn = self::NAME_COLUMN;

        $query->where(static function (Builder $group) use ($columnId, $names, $matchesBlank, $nameColumn): void {
            if ($names !== []) {
                $group->whereHas($columnId, static function (Builder $related) use ($names, $nameColumn): void {
                    $related->whereIn($nameColumn, $names);
                });
            }

            if ($matchesBlank) {
                $group->orWhereDoesntHave($columnId);
            }
        });

        return true;
    }

    /**
     * Excel-like distinct names among the functions matching `$query`
     * (already scoped by every OTHER active filter).
     *
     * @param  Builder<BusinessFunction>  $query
     * @return array<int, string|null>
     */
    public function distinctValues(Builder $query, string $columnId, ?string $search, int $limit): array
    {
        $nameColumn = self::NAME_COLUMN;
        $functionIds = (clone $query)->select('business_functions.id');

        $related = Company::query()->whereIn('id', DB::table('business_function_company')
            ->whereIn('business_function_id', $functionIds)
            ->select('company_id'));

        $values = $related
            ->when($search !== null && $search !== '', function (Builder $builder) use ($search, $nameColumn): void {
                $builder->where($nameColumn, 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy($nameColumn)
            ->limit($limit)
            ->pluck($nameColumn)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave($columnId)
            ->exists());
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
