<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * The latest change made on qnet-2 to a record (spec 0375 D-8). A `created` entry
 * does not count: referents and registries go through their services at import
 * and every one of them carries it.
 */
final class LegacyRealignTouchGuard
{
    private const string IMPORT_EVENT = 'created';

    /**
     * @param  list<array{0: class-string<Model>, 1: int|string|Builder<Model>|null}>  $subjects
     */
    public function lastChange(array $subjects): ?Activity
    {
        return Activity::query()
            ->where(fn (Builder $query): Builder => $query->whereNull('event')->orWhere('event', '<>', self::IMPORT_EVENT))
            ->where(function (Builder $query) use ($subjects): void {
                foreach ($subjects as [$class, $ids]) {
                    if ($ids !== null) {
                        $query->orWhere(fn (Builder $subject): Builder => $this->matches($subject, $class, $ids));
                    }
                }
            })
            ->latest('id')
            ->first();
    }

    /**
     * @param  class-string<Model>  $class
     * @param  int|string|Builder<Model>  $ids
     */
    private function matches(Builder $query, string $class, int|string|Builder $ids): Builder
    {
        $query->where('subject_type', (new $class)->getMorphClass());

        return $ids instanceof Builder ? $query->whereIn('subject_id', $ids) : $query->where('subject_id', $ids);
    }
}
