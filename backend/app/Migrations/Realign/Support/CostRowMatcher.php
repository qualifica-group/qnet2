<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Pairs legacy costs with local ones (spec 0375 D-13): costs have no legacy anchor,
 * so they meet on product and imputed line. Rows identical on both sides cancel out;
 * what remains is one to one (update), legacy only (create) or unclear (report only).
 *
 * @template TRow of array<string, mixed>
 * @template TLocal of Model
 */
final class CostRowMatcher
{
    /**
     * @param  list<TRow>  $legacyRows
     * @param  iterable<TLocal>  $locals
     * @param  Closure(TRow): string  $rowKey
     * @param  Closure(TLocal): string  $localKey
     * @param  Closure(TRow, TLocal): bool  $sameValues
     * @param  Closure(TRow): bool  $isRecent
     * @param  Closure(TLocal): bool  $canUpdate
     * @return array{update: list<array{0: TRow, 1: TLocal}>, create: list<TRow>, ambiguous: list<TRow>}
     */
    public function plan(array $legacyRows, iterable $locals, Closure $rowKey, Closure $localKey, Closure $sameValues, Closure $isRecent, Closure $canUpdate): array
    {
        $plan = ['update' => [], 'create' => [], 'ambiguous' => []];
        $localsByKey = [];

        foreach ($locals as $local) {
            $localsByKey[$localKey($local)][] = $local;
        }

        foreach ($this->groupBy($legacyRows, $rowKey) as $key => $rows) {
            [$rows, $remaining] = $this->withoutIdentical($rows, $localsByKey[$key] ?? [], $sameValues);
            $rows = array_values(array_filter($rows, $isRecent));

            if ($rows === []) {
                continue;
            }

            if ($remaining === []) {
                array_push($plan['create'], ...$rows);
            } elseif (count($rows) === 1 && count($remaining) === 1 && $canUpdate($remaining[0])) {
                $plan['update'][] = [$rows[0], $remaining[0]];
            } else {
                array_push($plan['ambiguous'], ...$rows);
            }
        }

        return $plan;
    }

    /**
     * @param  list<TRow>  $rows
     * @param  Closure(TRow): string  $rowKey
     * @return array<string, list<TRow>>
     */
    private function groupBy(array $rows, Closure $rowKey): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$rowKey($row)][] = $row;
        }

        return $groups;
    }

    /**
     * @param  list<TRow>  $rows
     * @param  list<TLocal>  $locals
     * @param  Closure(TRow, TLocal): bool  $sameValues
     * @return array{0: list<TRow>, 1: list<TLocal>}
     */
    private function withoutIdentical(array $rows, array $locals, Closure $sameValues): array
    {
        $unmatched = [];

        foreach ($rows as $row) {
            $index = array_find_key($locals, static fn (Model $local): bool => $sameValues($row, $local));

            if ($index === null) {
                $unmatched[] = $row;
            } else {
                unset($locals[$index]);
            }
        }

        return [$unmatched, array_values($locals)];
    }
}
