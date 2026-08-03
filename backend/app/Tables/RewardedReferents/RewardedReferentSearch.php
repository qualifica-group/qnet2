<?php

declare(strict_types=1);

namespace App\Tables\RewardedReferents;

use App\Models\Referent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Derived global quick-search (spec 0009) for the `rewarded-referents`
 * domain's `name` column. `Referent` carries only a denormalized display
 * `name` (`referent_has_no_first_last_name`): the real first/last name lives
 * on the `personalData` card, so a search for "Bianchi" must ALSO match
 * `personalData.last_name` even when `referents.name` does not contain it
 * (AC-009). Mirrors RequestClientColumns' `orWhereHas` precedent.
 *
 * SECURITY: `$pattern` is the engine's already LIKE-escaped, `%…%`-wrapped
 * bound parameter — never interpolated.
 */
final class RewardedReferentSearch
{
    /**
     * Add the OR-branch for the `name` column's extended search to the
     * engine's search group. Returns false for any other column, so the
     * generic engine handles it (though the catalogue declares no other
     * searchable column today).
     *
     * @param  Builder<Referent>  $query
     */
    public function apply(Builder $query, string $columnId, string $pattern): bool
    {
        if ($columnId !== 'name') {
            return false;
        }

        $query->orWhere('referents.name', 'like', $pattern)
            ->orWhereHas('personalData', static function (Builder $cardQuery) use ($pattern): void {
                $cardQuery->where('first_name', 'like', $pattern)
                    ->orWhere('last_name', 'like', $pattern);
            });

        return true;
    }
}
