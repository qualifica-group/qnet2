<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Concurrency-safe sequential code generator (spec 0023, BR-1):
 * "{prefix}-{seq:4}", zero-padded to 4 digits, no reuse of holes.
 *
 * MUST be called from inside an already-open DB::transaction (the caller's
 * create()): the pessimistic lock taken here (`FOR UPDATE`) is released only
 * when that transaction commits/rolls back, which is what makes two
 * concurrent creates serialize on the same next code instead of racing.
 * Generic on $table/$column/$prefix so both ProjectService (PRJ-) and
 * CampaignService (CMP-) share one implementation.
 */
trait GeneratesSequentialCode
{
    private const int CODE_PAD_LENGTH = 4;

    /**
     * The next "{prefix}-0001" code for $table.$column.
     */
    protected function nextSequentialCode(string $table, string $column, string $prefix): string
    {
        // Locks every row matching this prefix (defence in depth against a
        // concurrent create racing on the same next sequence); a genuinely
        // empty prefix locks nothing and relies on the column's unique
        // constraint to reject a rare double-first-insert race.
        $lastCode = $this->lastCodeQuery($table, $column, $prefix)
            ->lockForUpdate()
            ->value($column);

        return $this->formatNextCode(is_string($lastCode) ? $lastCode : null, $prefix);
    }

    /**
     * The next "{prefix}-0001" code as a NON-binding suggestion for the create
     * form's auto-fill: a plain, lock-free read (no transaction, no
     * FOR UPDATE) since it never persists anything. Two concurrent previews
     * may propose the same code; the real, serialized assignment still happens
     * in nextSequentialCode()/the unique constraint at store time.
     */
    protected function peekNextSequentialCode(string $table, string $column, string $prefix): string
    {
        $lastCode = $this->lastCodeQuery($table, $column, $prefix)->value($column);

        return $this->formatNextCode(is_string($lastCode) ? $lastCode : null, $prefix);
    }

    /**
     * The codes of $prefix, numerically highest first. Only the generated
     * shape "{prefix}-{digits}" counts: a manual code is free text (D-13), and
     * one sharing the prefix ("QUO-0004 copia", "QUO-0004/1") must never be
     * read as the last of the sequence, or every create proposes a code that
     * is already taken. Ordered by the numeric value, not the string: the
     * sequence is padded to a MINIMUM width (past 9999 it grows a digit) and a
     * manual "QUO-00004" is longer than "QUO-0005" yet numerically lower.
     */
    private function lastCodeQuery(string $table, string $column, string $prefix): Builder
    {
        $wrappedColumn = DB::getQueryGrammar()->wrap($column);
        $sequence = 'SUBSTR('.$wrappedColumn.', '.(strlen($prefix) + 2).')';

        $query = DB::table($table)->where($column, 'like', $prefix.'-%');

        // SQLite (test suite) has no REGEXP and no UNSIGNED cast: GLOB pair
        // "prefix-, at least one digit, no non-digit" and an INTEGER cast.
        if (DB::getDriverName() === 'sqlite') {
            return $query
                ->whereRaw($wrappedColumn.' GLOB ?', [$prefix.'-[0-9]*'])
                ->whereRaw($wrappedColumn.' NOT GLOB ?', [$prefix.'-*[^0-9]*'])
                ->orderByRaw('CAST('.$sequence.' AS INTEGER) DESC');
        }

        return $query
            ->whereRaw($wrappedColumn.' REGEXP ?', ['^'.$prefix.'-[0-9]+$'])
            ->orderByRaw('CAST('.$sequence.' AS UNSIGNED) DESC');
    }

    /**
     * Formats the sequence following $lastCode (null = first ever) as
     * "{prefix}-{seq:4}".
     */
    private function formatNextCode(?string $lastCode, string $prefix): string
    {
        $nextSequence = $lastCode === null
            ? 1
            : ((int) substr($lastCode, strlen($prefix) + 1)) + 1;

        return $this->formatSequentialCode($prefix, $nextSequence);
    }

    /**
     * "{prefix}-{seq:4}" for $sequence — for a caller that assigns a whole run
     * of codes after nextSequentialCode() (the bulk sample seeder).
     */
    protected function formatSequentialCode(string $prefix, int $sequence): string
    {
        return sprintf('%s-%0'.self::CODE_PAD_LENGTH.'d', $prefix, $sequence);
    }
}
