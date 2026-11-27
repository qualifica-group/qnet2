<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Products\ProductCategoryBranches;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Spec 0411: an offer created from Gestione Richieste is bound to ONE root
 * product category ("categoria madre", `quotes.root_product_category_id`) —
 * one offer per root of the request's product lines. A bound offer is
 * classified only by the opportunity lines under its root; an unbound one
 * (null, every pre-0411 offer) keeps reading all of them.
 *
 * The one place that knows the rule, for both sides: the reads (in-memory
 * line filter, SQL constraint on joined `opportunity_product_lines`) and the
 * edit blocks (D-4, D-5). Root resolution reads the tree once per instance
 * (ProductCategoryBranches), never a query per line.
 */
final class QuoteRootScope
{
    public const string QUOTE_ROOT_COLUMN = 'quotes.root_product_category_id';

    /** D-4: a bound offer's product lines stay under its root. */
    public const string OTHER_ROOT_MESSAGE = 'This request belongs to the product category :root: its product lines must stay under it. For another product category create a new request.';

    /** D-5: the opportunity's roots are fixed by its bound offers. */
    public const string BOUND_ROOTS_MESSAGE = 'The offers of this opportunity are bound to the product categories :roots: the product lines cannot add or remove a parent product category. For another product category create a new request.';

    /** Spec 0432: an unbound request with old-catalogue lines keeps the roots it has. */
    public const string LEGACY_ROOTS_MESSAGE = 'This request has product lines of the old catalogue: its product lines can only be under :roots. For another product category create a new request.';

    /** D-6: an offer row of a bound offer stays under its root. */
    public const string OFFER_LINE_ROOT_MESSAGE = 'This offer belongs to the product category :root: the product ":product" belongs to another one.';

    /**
     * @var array<int, int>|null
     */
    private ?array $boundRootIds = null;

    public function __construct(private readonly ProductCategoryBranches $branches) {}

    public function rootIdOf(int $categoryId): int
    {
        return $this->branches->rootIdOf($categoryId);
    }

    /**
     * The category ids grouped by root, roots in order of first appearance.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, array<int, int>> root id => its category ids
     */
    public function groupByRoot(array $categoryIds): array
    {
        $groups = [];

        foreach ($categoryIds as $categoryId) {
            $groups[$this->rootIdOf((int) $categoryId)][] = (int) $categoryId;
        }

        return $groups;
    }

    /**
     * The opportunity lines $quote is classified by. Assumes
     * `opportunity.productLines` is loaded (or loads it once).
     *
     * @return Collection<int, OpportunityProductLine>
     */
    public function linesOf(Quote $quote): Collection
    {
        if ($quote->opportunity_id === null) {
            return new Collection;
        }

        $lines = $quote->loadMissing('opportunity.productLines')->opportunity->productLines;

        return $this->filterLines($lines, $quote->root_product_category_id);
    }

    /**
     * @param  Collection<int, OpportunityProductLine>  $lines
     * @return Collection<int, OpportunityProductLine>
     */
    public function filterLines(Collection $lines, ?int $rootId): Collection
    {
        if ($rootId === null) {
            return $lines;
        }

        return $lines
            ->filter(fn (OpportunityProductLine $line): bool => $this->rootIdOf((int) $line->product_category_id) === $rootId)
            ->values();
    }

    /**
     * Restricts rows that join `opportunity_product_lines` onto `quotes` to
     * the lines each offer is classified by. No-op while no offer is bound.
     *
     * @param  Builder  $query  any builder whose rows carry both columns
     */
    public function constrainJoinedLines(Builder $query, string $lineCategoryColumn, string $quoteRootColumn = self::QUOTE_ROOT_COLUMN): void
    {
        $roots = $this->boundRootIds();

        if ($roots === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($roots, $lineCategoryColumn, $quoteRootColumn): void {
            $outer->whereNull($quoteRootColumn);

            foreach ($roots as $rootId) {
                $outer->orWhere(fn (Builder $bound) => $bound
                    ->where($quoteRootColumn, $rootId)
                    ->whereIn($lineCategoryColumn, $this->branches->branchIds($rootId)));
            }
        });
    }

    /**
     * D-4: every submitted category of a bound offer sits under its root.
     *
     * @param  array<int, int>  $categoryIds
     *
     * @throws ValidationException
     */
    public function assertUnderRoot(array $categoryIds, int $rootId, string $errorField): void
    {
        foreach ($categoryIds as $categoryId) {
            if ($this->rootIdOf((int) $categoryId) !== $rootId) {
                throw ValidationException::withMessages([
                    $errorField => [__(self::OTHER_ROOT_MESSAGE, ['root' => $this->namesOf([$rootId])])],
                ]);
            }
        }
    }

    /**
     * Spec 0432: an UNBOUND request whose lines include an old-catalogue
     * category (`is_legacy`, on the category or on its root) is locked to the
     * roots of those lines; [] when none is legacy, so a new-catalogue request
     * stays free.
     *
     * @param  array<int, int>  $currentCategoryIds
     * @return array<int, int>
     */
    public function legacyRootIdsOf(array $currentCategoryIds): array
    {
        $rootIds = array_keys($this->groupByRoot($currentCategoryIds));

        $hasLegacy = $rootIds !== [] && ProductCategory::query()
            ->whereKey([...$currentCategoryIds, ...$rootIds])
            ->where('is_legacy', true)
            ->exists();

        return $hasLegacy ? $rootIds : [];
    }

    /**
     * Spec 0432: the roots a request's work panel offers when it is a legacy
     * one, by name; empty for a bound offer (D-4 locks it already) and for a
     * request of the new catalogue.
     *
     * @return Collection<int, ProductCategory>
     */
    public function legacyRootsOf(Quote $quote): Collection
    {
        if ($quote->root_product_category_id !== null) {
            return new Collection;
        }

        $categoryIds = $this->linesOf($quote)->pluck('product_category_id')->map(intval(...))->all();
        $rootIds = $this->legacyRootIdsOf($categoryIds);

        return $rootIds === []
            ? new Collection
            : ProductCategory::query()->whereKey($rootIds)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Spec 0432: every submitted category of a legacy request sits under one
     * of the roots its persisted lines already have.
     *
     * @param  array<int, int>  $nextCategoryIds
     * @param  array<int, int>  $currentCategoryIds
     *
     * @throws ValidationException
     */
    public function assertWithinLegacyRoots(array $nextCategoryIds, array $currentCategoryIds, string $errorField): void
    {
        $rootIds = $this->legacyRootIdsOf($currentCategoryIds);

        if ($rootIds === []) {
            return;
        }

        foreach ($nextCategoryIds as $categoryId) {
            if (! in_array($this->rootIdOf((int) $categoryId), $rootIds, true)) {
                throw ValidationException::withMessages([
                    $errorField => [__(self::LEGACY_ROOTS_MESSAGE, ['roots' => $this->namesOf($rootIds)])],
                ]);
            }
        }
    }

    /**
     * D-5: when $opportunity has bound offers, the new line set must cover
     * exactly their roots — no root without an offer, no bound root dropped.
     *
     * @param  array<int, int>  $nextCategoryIds
     *
     * @throws ValidationException
     */
    public function assertOpportunityKeepsBoundRoots(Opportunity $opportunity, array $nextCategoryIds, string $errorField): void
    {
        $bound = $opportunity->quotes()
            ->whereNotNull('root_product_category_id')
            ->distinct()
            ->pluck('root_product_category_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($bound === []) {
            return;
        }

        $next = array_keys($this->groupByRoot($nextCategoryIds));
        sort($next);

        if ($next === $bound) {
            return;
        }

        throw ValidationException::withMessages([
            $errorField => [__(self::BOUND_ROOTS_MESSAGE, ['roots' => $this->namesOf($bound)])],
        ]);
    }

    /**
     * D-6: the products of a bound offer's rows sit under its root.
     *
     * @param  iterable<int, Product>  $products
     *
     * @throws ValidationException
     */
    public function assertProductsUnderRoot(Quote $quote, iterable $products, string $errorField): void
    {
        $rootId = $quote->root_product_category_id;

        if ($rootId === null) {
            return;
        }

        foreach ($products as $product) {
            if ($product->category_id !== null && $this->rootIdOf((int) $product->category_id) !== $rootId) {
                throw ValidationException::withMessages([
                    $errorField => [__(self::OFFER_LINE_ROOT_MESSAGE, [
                        'root' => $this->namesOf([$rootId]),
                        'product' => $product->name,
                    ])],
                ]);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function boundRootIds(): array
    {
        return $this->boundRootIds ??= Quote::query()
            ->whereNotNull('root_product_category_id')
            ->distinct()
            ->pluck('root_product_category_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function namesOf(array $categoryIds): string
    {
        return ProductCategory::query()->whereKey($categoryIds)->orderBy('name')->pluck('name')->implode(', ');
    }
}
