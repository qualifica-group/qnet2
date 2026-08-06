<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The QUERY side of the computed Opportunity status (spec 0082, re-targeted
 * at the Quote workflow by spec 0083 D-2/D-8): the single expression of "the
 * status this row DISPLAYS matches ...", mirroring
 * OpportunityStatusResolver's read-side rules so a filter can never disagree
 * with the badge next to it.
 *
 * The predicate is always the same OR: the row has a quote in a matching
 * workflow status, or — only when it has NO quote at all — the GLOBAL
 * default workflow set's `open` row itself matches (D-8: every quote-less
 * opportunity displays that SAME row, never a per-row lookup).
 *
 * Values are always bound through `whereIn`/parameter binding — never
 * interpolated (backend.md §8).
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
            $outer->whereHas('quotes.quoteWorkflowStatus', static function (Builder $related) use ($column, $values): void {
                $related->whereIn($column, $values);
            });

            // D-8: a quote-less opportunity always displays the GLOBAL
            // default set's `open` row — it matches the filter only when
            // THAT row's own value is among $values, never per-row.
            if (self::defaultOpenMatches($column, $values)) {
                $outer->orWhereDoesntHave('quotes');
            }
        });
    }

    /**
     * @param  array<int, string>  $values
     */
    private static function defaultOpenMatches(string $column, array $values): bool
    {
        $value = QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
            ->where('system_key', WorkflowStatusSystemKey::Open->value)
            ->value($column);

        if ($value === null) {
            return false;
        }

        // `group` is cast to WorkflowStatusGroup on the model, so
        // Builder::value() (which hydrates via first()) returns the enum,
        // not the raw string, for that column — `name` stays a plain string.
        $scalar = $value instanceof WorkflowStatusGroup ? $value->value : $value;

        return in_array($scalar, $values, true);
    }
}
