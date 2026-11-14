<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * User directive 2026-08-31: an anagrafica may carry only ONE opportunity
 * still running at a time — creating a second one while an open one exists is
 * refused, and the refusal names the opportunity that blocks it so the client
 * can link straight to it.
 *
 * "Open" is NOT a column: the Opportunity's status is COMPUTED from its quotes
 * (spec 0082/0083, OpportunityStatusResolver). The predicate therefore reuses
 * OpportunityStatusScope — the query side of that same computation — with
 * ACTIVE_GROUPS, so "aperta" here means exactly what the badge and the table
 * filter mean: at least one quote outside the two terminal outcomes
 * (`closed_won`/`closed_lost`), or no quote at all (which displays the `open`
 * row of the workflow its own product category resolves to).
 *
 * User directive 2026-11-07: an open opportunity whose product lines all sit on
 * legacy categories (`product_categories.is_legacy`) does not block; user
 * report 2026-11-13: nor does a migrated one (`old_id`) without any line.
 *
 * User decision 2026-11-11: the exemption is the root-owned category flag
 * `multiple_open_opportunities`, in both directions — an open opportunity of
 * a flagged branch does not block, and a NEW one of a flagged branch is
 * created beside any open one. It replaces the former `single`-mode exemption
 * (directives 2026-08-31 and 2026-11-07): the migration turned the flag on
 * for the `single` categories, then it is edited by hand, independently of
 * the management mode (so a `multiple` branch, whose offers carry several
 * rows, may be exempt too). An opportunity is exempt when every one of its
 * current (non-legacy) categories carries the flag: one category without it
 * keeps the block.
 *
 * Enforced inside OpportunityService::create(), the single write point every
 * creation path funnels through (form, lead conversion, import auto-convert),
 * so no path can bypass it. The bulk conversion pre-checks the same predicate
 * (ConvertLeadsToOpportunities) only to report every offending lead at once.
 */
final class RegistryOpenOpportunityGuard
{
    /**
     * User directive 2026-08-31: the refusal must POINT SOMEWHERE — the open
     * opportunity is where the offer belongs, so the message says to add it
     * there rather than to close anything (the client turns the id below into
     * a one-click "add the offer on it" link).
     */
    public const string OPEN_OPPORTUNITY_MESSAGE = 'This registry already has an open opportunity: :opportunity. Add the offer to that one instead of creating a duplicate.';

    /**
     * Carried alongside the `registry_id` field error so the client can link
     * to the blocking opportunity (the URL is built client-side: the API never
     * emits frontend paths).
     */
    public const string EXISTING_OPPORTUNITY_KEY = 'existing_opportunity_id';

    /**
     * @param  array<int, int>  $newCategoryIds  the product categories of the opportunity being created
     *
     * @throws ValidationException when $registryId already owns a blocking open opportunity
     */
    public function assertNoOpenOpportunity(int $registryId, array $newCategoryIds = []): void
    {
        $existing = $this->blockingOpportunityFor($registryId, $newCategoryIds);

        if ($existing === null) {
            return;
        }

        throw ValidationException::withMessages([
            'registry_id' => [__(self::OPEN_OPPORTUNITY_MESSAGE, ['opportunity' => $existing->name])],
            self::EXISTING_OPPORTUNITY_KEY => [(string) $existing->id],
        ]);
    }

    /**
     * The opportunity that blocks creating a new one for $registryId with the
     * given categories, or null when the creation is allowed (new categories
     * all exempt, or no blocking open opportunity). The single predicate behind
     * both the write-time refusal and the read-only pre-check.
     *
     * @param  array<int, int>  $newCategoryIds  the product categories of the opportunity being created
     */
    public function blockingOpportunityFor(int $registryId, array $newCategoryIds = []): ?Opportunity
    {
        if ($this->allowMultipleOpen(ProductCategory::query()->whereKey($newCategoryIds)->get(['id', 'is_legacy', 'multiple_open_opportunities']))) {
            return null;
        }

        return $this->openOpportunityFor($registryId);
    }

    /**
     * The blocking opportunity of every registry among $registryIds that has
     * one, keyed by `registry_id` — the batch form of openOpportunityFor(),
     * for callers pre-checking a whole selection.
     *
     * @param  array<int, int>  $registryIds
     * @return array<int, int> registry_id => opportunity id
     */
    public function openOpportunityIdsByRegistry(array $registryIds): array
    {
        /** @var array<int, int> $map */
        $map = $this->blockingOpportunities($registryIds)
            ->mapWithKeys(static fn (Opportunity $opportunity): array => [
                (int) $opportunity->registry_id => (int) $opportunity->id,
            ])
            ->all();

        return $map;
    }

    /**
     * The oldest open opportunity of $registryId that blocks a new one, or
     * null when the anagrafica has none (no open opportunity at all, or only
     * ones whose branch allows several).
     */
    public function openOpportunityFor(int $registryId): ?Opportunity
    {
        return $this->blockingOpportunities([$registryId])->first();
    }

    /**
     * The open opportunities of $registryIds that actually block, in id order:
     * those whose branch allows several open opportunities are dropped.
     *
     * @param  array<int, int>  $registryIds
     * @return Collection<int, Opportunity>
     */
    private function blockingOpportunities(array $registryIds): Collection
    {
        if ($registryIds === []) {
            /** @var Collection<int, Opportunity> $empty */
            $empty = new Collection;

            return $empty;
        }

        // User directive 2026-11-07: an opportunity whose product lines all sit on
        // legacy categories (`is_legacy`, the migrated catalogue) is history, not
        // running business, so it never blocks. User report 2026-11-13: neither
        // does a MIGRATED one (`old_id`) with no line at all — the legacy record
        // had no category, so no flag can ever exempt it. A native one cannot
        // lose its lines (`product_lines` min:1), so it still blocks if lineless.
        $opportunities = Opportunity::query()
            ->whereIn('registry_id', $registryIds)
            ->where(static fn ($query) => $query
                ->where(static fn ($lineless) => $lineless->whereNull('old_id')->whereDoesntHave('productLines'))
                ->orWhereHas('productLines.productCategory', static fn ($category) => $category->where('is_legacy', false)));

        // The quote-less branch resolves a workflow per row (it is the row's
        // own product category that decides it), so it is handed the SAME
        // registry restriction instead of every quote-less opportunity there
        // is — this runs on every opportunity creation.
        OpportunityStatusScope::whereGroupIn(
            $opportunities,
            OpportunityStatusScope::ACTIVE_GROUPS,
            Opportunity::query()->whereIn('registry_id', $registryIds),
        );

        return $opportunities
            ->with('productLines.productCategory:id,is_legacy,multiple_open_opportunities')
            ->orderBy('id')
            ->get()
            ->reject(fn (Opportunity $opportunity): bool => $this->allowMultipleOpen($opportunity->productLines->map(
                static fn (OpportunityProductLine $line): ?ProductCategory => $line->productCategory,
            )->filter()))
            ->values();
    }

    /**
     * Whether the current (non-legacy) categories among $categories all allow
     * several open opportunities per anagrafica. False when there is none:
     * an opportunity without a current category is judged by the other rules.
     *
     * @param  Collection<int, ProductCategory>  $categories
     */
    private function allowMultipleOpen(Collection $categories): bool
    {
        $current = $categories->reject(static fn (ProductCategory $category): bool => (bool) $category->is_legacy);

        return $current->isNotEmpty()
            && $current->every(static fn (ProductCategory $category): bool => (bool) $category->multiple_open_opportunities);
    }
}
