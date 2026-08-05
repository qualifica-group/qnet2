<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The QUERY side of the computed Opportunity status (spec 0082): the single
 * expression of "the status this row DISPLAYS matches ...", mirroring
 * OpportunityStatusResolver's read-side rules so a filter can never disagree
 * with the badge next to it.
 *
 * The predicate is always the same OR: the row has a quote in a matching
 * status, or — only when it has NO quote at all — its working state matches.
 * `quote_statuses` and `opportunity_workflow_statuses` share both the `name`
 * and `group` column names and the same group vocabulary (`open`/`pending`/
 * `closed_won`/`closed_lost`, plus the workflow-only `validated`), which is
 * what lets one predicate span the two tables.
 *
 * Values are always bound through `whereIn` — never interpolated
 * (backend.md §8).
 */
final class OpportunityStatusScope
{
    /**
     * The groups that count as still-running work: everything that is not a
     * terminal outcome, the workflow-only `validated` phase included.
     *
     * @var array<int, string>
     */
    public const array ACTIVE_GROUPS = ['open', 'pending', 'validated'];

    /**
     * The terminal groups, whatever their outcome.
     *
     * @var array<int, string>
     */
    public const array CLOSED_GROUPS = ['closed_won', 'closed_lost'];

    /**
     * @param  Builder<Model>  $opportunities
     * @param  array<int, string>  $names
     */
    public static function whereNameIn(Builder $opportunities, array $names): void
    {
        self::whereDisplayed($opportunities, 'name', $names);
    }

    /**
     * @param  Builder<Model>  $opportunities
     * @param  array<int, string>  $groups
     */
    public static function whereGroupIn(Builder $opportunities, array $groups): void
    {
        self::whereDisplayed($opportunities, 'group', $groups);
    }

    /**
     * @param  Builder<Model>  $opportunities
     * @param  array<int, string>  $values
     */
    private static function whereDisplayed(Builder $opportunities, string $column, array $values): void
    {
        if ($values === []) {
            return;
        }

        $opportunities->where(static function (Builder $outer) use ($column, $values): void {
            $outer->whereHas('quotes.quoteStatus', static function (Builder $related) use ($column, $values): void {
                $related->whereIn($column, $values);
            });

            $outer->orWhere(static function (Builder $fallback) use ($column, $values): void {
                $fallback->whereDoesntHave('quotes')
                    ->whereHas('workflowStatus', static function (Builder $related) use ($column, $values): void {
                        $related->whereIn($column, $values);
                    });
            });
        });
    }
}
