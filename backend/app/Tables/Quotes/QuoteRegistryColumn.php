<?php

declare(strict_types=1);

namespace App\Tables\Quotes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The `registry` (Anagrafica) column of the `quotes` grid: an offer has no
 * `registry_id` of its own, so the client is reached through
 * `quotes.opportunity_id` -> `opportunities.registry_id`, both NOT NULL, hence
 * no blank entry in the set filter. Read-only: the client belongs to the
 * Opportunity. Split out of QuoteRelationColumns, whose map only covers
 * single-hop relations.
 */
final class QuoteRegistryColumn
{
    public const string COLUMN_ID = 'registry';

    private const string RELATION_PATH = 'opportunity.registry';

    private const string LABEL_COLUMN = 'name';

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public function applyFilter(Builder $query, array $values): void
    {
        if ($values === []) {
            return;
        }

        $query->whereHas(self::RELATION_PATH, static function (Builder $registries) use ($values): void {
            $registries->whereIn(self::LABEL_COLUMN, $values);
        });
    }

    /**
     * Quick-search on the client's name (task 427126), OR-ed into the engine's search group.
     *
     * @param  Builder<Model>  $query
     */
    public function applySearch(Builder $query, string $pattern): void
    {
        $query->orWhereHas(self::RELATION_PATH, static function (Builder $registries) use ($pattern): void {
            $registries->where(self::LABEL_COLUMN, 'like', $pattern);
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy(
            DB::table('registries')
                ->select('registries.'.self::LABEL_COLUMN)
                ->join('opportunities', 'opportunities.registry_id', '=', 'registries.id')
                ->whereColumn('opportunities.id', 'quotes.opportunity_id')
                ->limit(1),
            $direction,
        );
    }

    /**
     * Distinct client names among the offers matching $query (spec 0004/0005).
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(?string $search, Builder $query, int $limit): array
    {
        $opportunityIds = (clone $query)->select('quotes.opportunity_id')->toBase();
        $registryIds = DB::table('opportunities')->whereIn('id', $opportunityIds)->select('registry_id');

        return DB::table('registries')
            ->whereIn('id', $registryIds)
            ->when($search !== null && $search !== '', function (QueryBuilder $builder) use ($search): void {
                $builder->where(self::LABEL_COLUMN, 'like', '%'.$this->escapeLike($search).'%');
            })
            ->groupBy(self::LABEL_COLUMN)
            ->orderBy(self::LABEL_COLUMN)
            ->limit($limit)
            ->pluck(self::LABEL_COLUMN)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
