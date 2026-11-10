<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One `activity_log` entry for what the model log cannot see (spec 0372, D-7): the people pivots
 * (Responsabili, Partecipanti, Gestori) and `attribute_values` are outside `#[Fillable]`, so
 * `logFillable` never records them. A save from the app calls capture() before writing and log()
 * after: the entry carries only the keys that really differ, in the `attributes`/`old` shape the
 * module already uses (`manager_slots` is the ordered, gap-aware user list; `supervisors` is the
 * id list; `participants` the slot list). Nothing differs, nothing is logged.
 *
 * Silent during an import: it runs under `activity()->withoutLogs`, which this entry obeys like
 * any other. The legacy-record touch guard reads these entries (LegacyRecordTouchGuard).
 */
final class PeopleChangeLog
{
    private const string DESCRIPTION = 'People and request fields change';

    /**
     * @return list<array{0: Model, 1: array<string, mixed>}> the subjects with their state before the write
     */
    public static function capture(Model ...$subjects): array
    {
        return array_map(static fn (Model $subject): array => [$subject, self::snapshot($subject)], $subjects);
    }

    /**
     * @param  list<array{0: Model, 1: array<string, mixed>}>  $captured  as returned by capture()
     */
    public static function log(array $captured): void
    {
        foreach ($captured as [$subject, $before]) {
            $after = self::snapshot($subject);
            $changed = array_filter($after, static fn (mixed $value, string $key): bool => $value !== $before[$key], ARRAY_FILTER_USE_BOTH);

            if ($changed === []) {
                continue;
            }

            activity($subject->getTable())
                ->performedOn($subject)
                ->event('updated')
                ->withProperties([
                    'attributes' => $changed,
                    'old' => array_intersect_key($before, $changed),
                ])
                ->log(self::DESCRIPTION);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshot(Model $subject): array
    {
        return match (true) {
            $subject instanceof WorkOrder => [
                'supervisors' => self::sortedIds($subject->supervisors()),
                'participants' => self::slots($subject->participants()),
                'attribute_values' => $subject->attribute_values ?? [],
            ],
            $subject instanceof Quote => [
                'manager_slots' => self::slots($subject->managers()),
                'attribute_values' => $subject->attribute_values ?? [],
            ],
            $subject instanceof Opportunity => ['manager_slots' => self::slots($subject->managers())],
            default => [],
        };
    }

    /**
     * @return array<int, int>
     */
    private static function sortedIds(BelongsToMany $relation): array
    {
        $ids = $relation->pluck('users.id')->map(intval(...))->all();
        sort($ids);

        return $ids;
    }

    /**
     * The positional pivot as the ordered, gap-aware user list (index + 1 = position).
     *
     * @return array<int, int|null>
     */
    private static function slots(BelongsToMany $relation): array
    {
        $positions = array_flip(ManagerPositions::positionsOf($relation->get()));

        if ($positions === []) {
            return [];
        }

        $slots = array_fill(0, max(array_keys($positions)), null);

        foreach ($positions as $position => $userId) {
            $slots[$position - 1] = $userId;
        }

        return $slots;
    }
}
