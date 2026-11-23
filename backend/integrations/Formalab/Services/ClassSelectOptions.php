<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use Illuminate\Database\Eloquent\Builder;
use Integrations\Formalab\Models\FormalabClass;

/**
 * The options of the work order class select (spec 0426 D-4): the local
 * catalogue, newest start first, searched on code, title, label and, for a
 * number, the edition id. The requested ids[] are always appended so a saved
 * value renders even outside the page.
 */
final class ClassSelectOptions
{
    public function handle(ForSelectQuery $query): ForSelectResult
    {
        // Step 1: the search
        $base = FormalabClass::query();

        if ($query->hasSearch()) {
            $this->applySearch($base, (string) $query->search);
        }

        // Step 2: count and page
        $total = (clone $base)->count();
        $page = $this->ordered($base)->offset($query->offset)->limit($query->limit)->get();

        // Step 3: the ids the form needs to hydrate
        $missing = $query->hasIds() ? array_values(array_diff($query->ids, $page->pluck('edition_id')->all())) : [];

        if ($missing !== []) {
            $page = $page->concat($this->ordered(FormalabClass::query()->whereIn('edition_id', $missing))->get());
        }

        return new ForSelectResult(items: $page, total: $total, offset: $query->offset, limit: $query->limit);
    }

    /**
     * @param  Builder<FormalabClass>  $builder
     */
    private function applySearch(Builder $builder, string $search): void
    {
        $like = '%'.$search.'%';

        $builder->where(static function (Builder $where) use ($search, $like): void {
            $where->where('label', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('title', 'like', $like);

            if (ctype_digit($search)) {
                $where->orWhere('edition_id', (int) $search);
            }
        });
    }

    /**
     * @param  Builder<FormalabClass>  $builder
     * @return Builder<FormalabClass>
     */
    private function ordered(Builder $builder): Builder
    {
        return $builder->orderByDesc('start_date')->orderByDesc('edition_id');
    }
}
