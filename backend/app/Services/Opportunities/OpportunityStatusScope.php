<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Enums\WorkflowStatusGroup;
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
 * workflow status, or it has NO quote at all and the row
 * OpportunityDefaultStatusResolver resolves for it matches (D-8, user
 * directive 2026-09-08). That second branch is a per-row resolution — the
 * workflow a quote-less opportunity displays depends on its own product
 * category, and the winner is picked by a ranking (specificity, then branch
 * distance) that has no SQL form — so those ids are resolved in PHP and bound
 * back with a `whereIn`.
 *
 * WHICH quote-less rows get resolved is the caller's call, hence the
 * mandatory `$quoteLessSource`: a STANDALONE (never correlated) query the
 * resolution is allowed to EXECUTE. Callers with a narrowing already in hand
 * pass it (RegistryOpenOpportunityGuard: the registries it is asking about;
 * the grid: its own filtered query), a caller constraining a correlated
 * subquery — which cannot be executed on its own — passes the whole
 * `Opportunity::query()`. Passing more rows than needed only costs time, never
 * correctness: the ids land in an OR branch of a query that still applies
 * every other constraint on top.
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
     * @param  Builder<Model>  $quoteLessSource  standalone query the quote-less branch resolves over
     */
    public static function whereNameIn(Builder $opportunities, array $names, Builder $quoteLessSource): void
    {
        self::whereDisplayed($opportunities, 'name', $names, $quoteLessSource);
    }

    /**
     * @param  Builder<Model>  $opportunities
     * @param  array<int, string>  $groups
     * @param  Builder<Model>  $quoteLessSource  standalone query the quote-less branch resolves over
     */
    public static function whereGroupIn(Builder $opportunities, array $groups, Builder $quoteLessSource): void
    {
        self::whereDisplayed($opportunities, 'group', $groups, $quoteLessSource);
    }

    /**
     * @param  Builder<Model>  $opportunities
     * @param  array<int, string>  $values
     * @param  Builder<Model>  $quoteLessSource
     */
    private static function whereDisplayed(Builder $opportunities, string $column, array $values, Builder $quoteLessSource): void
    {
        if ($values === []) {
            return;
        }

        $quoteLessIds = self::quoteLessIdsMatching($quoteLessSource, $column, $values);

        $opportunities->where(static function (Builder $outer) use ($column, $values, $quoteLessIds): void {
            $outer->whereHas('quotes.quoteWorkflowStatus', static function (Builder $related) use ($column, $values): void {
                $related->whereIn($column, $values);
            });

            if ($quoteLessIds !== []) {
                $outer->orWhereIn('opportunities.id', $quoteLessIds);
            }
        });
    }

    /**
     * The ids of the quote-less rows of $source whose DISPLAYED status carries
     * one of $values in $column — the same rows, resolved by the same
     * collaborator, the badge would show that status on.
     *
     * @param  Builder<Model>  $source
     * @param  array<int, string>  $values
     * @return array<int, int>
     */
    private static function quoteLessIdsMatching(Builder $source, string $column, array $values): array
    {
        $statuses = app(OpportunityDefaultStatusResolver::class)->statusesForQuoteLess($source);

        return array_keys(array_filter(
            $statuses,
            static fn (QuoteWorkflowStatus $status): bool => in_array(self::displayedValue($status, $column), $values, true),
        ));
    }

    /**
     * $status' own value for the filtered $column. `group` is cast to
     * WorkflowStatusGroup on the model, so it is unwrapped to the raw string
     * the client filters on; `name` is already one.
     */
    private static function displayedValue(QuoteWorkflowStatus $status, string $column): string
    {
        $value = $status->getAttribute($column);

        return $value instanceof WorkflowStatusGroup ? $value->value : (string) $value;
    }
}
