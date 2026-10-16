<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Services\ProductCategories\CategoryRootResolver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Extra line rules of a multi-offer commessa (spec 0267, D-2/D-5), applied by
 * WorkOrderLineWriter AFTER the generic membership/"already programmed"
 * checks. A commessa that has no lines yet is being created: every line must
 * sit in a branch whose root allows multi-offer commesse, all lines in the
 * SAME branch, spanning at least two offers. One that already has lines is
 * being updated: only removal is allowed, down to at least one line.
 */
final class MultiQuoteLineRules
{
    public const int MIN_QUOTES = 2;

    public function __construct(private readonly CategoryRootResolver $roots) {}

    /**
     * @param  array<int, int>  $quoteLineIds
     */
    public function assert(WorkOrder $workOrder, array $quoteLineIds): void
    {
        $currentIds = $workOrder->quoteLines()->pluck('quote_lines.id')->map(static fn (mixed $id): int => (int) $id)->all();

        if ($currentIds !== []) {
            $this->assertRemovalOnly($currentIds, $quoteLineIds);

            return;
        }

        $this->assertCreatable($quoteLineIds);
    }

    /**
     * @param  array<int, int>  $currentIds
     * @param  array<int, int>  $quoteLineIds
     */
    private function assertRemovalOnly(array $currentIds, array $quoteLineIds): void
    {
        if ($quoteLineIds === [] || array_diff($quoteLineIds, $currentIds) !== []) {
            $this->fail(__('work_orders.multi_quote.lines_removal_only'));
        }
    }

    /**
     * @param  array<int, int>  $quoteLineIds
     */
    private function assertCreatable(array $quoteLineIds): void
    {
        /** @var Collection<int, QuoteLine> $lines */
        $lines = QuoteLine::query()->with('product.category')->whereIn('id', $quoteLineIds)->get();

        if ($lines->contains(static fn (QuoteLine $line): bool => $line->product?->category?->multi_quote_work_order !== true)) {
            $this->fail(__('work_orders.multi_quote.lines_not_allowed'));
        }

        $rootIds = collect($this->roots->rootSummariesFor($lines->map(static fn (QuoteLine $line): int => (int) $line->product->category_id)->all()))
            ->map(static fn (?array $root): ?int => $root['id'] ?? null)
            ->unique();

        if ($rootIds->count() !== 1 || $rootIds->first() === null) {
            $this->fail(__('work_orders.multi_quote.lines_different_branches'));
        }

        if ($lines->pluck('quote_id')->unique()->count() < self::MIN_QUOTES) {
            $this->fail(__('work_orders.multi_quote.lines_single_quote'));
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['quote_line_ids' => $message]);
    }
}
