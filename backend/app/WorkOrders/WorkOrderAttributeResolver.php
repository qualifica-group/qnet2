<?php

declare(strict_types=1);

namespace App\WorkOrders;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\AttributeLayoutMerger;
use App\RequestManagement\AttributeSetResolver;
use Illuminate\Support\Collection;

/**
 * The WorkOrder-level "applicable attributes"/layout (spec 0098, D-1/D-4):
 * resolved from the DISTINCT product categories of THIS work order's OWN
 * `quoteLines` — never the parent Quote's full `offerLines` set. Two work
 * orders on the same quote covering different-category lines therefore
 * resolve DIFFERENT sets (AC-008, proof of D-1).
 *
 * Thin caller of the generalized App\RequestManagement\AttributeSetResolver /
 * AttributeLayoutMerger (D-4): this class only knows how to turn a WorkOrder
 * (or a transient list of `quote_line_ids`, for the not-yet-saved
 * `form-context` preview) into an ordered, deduped category-id list; the
 * union/merge logic itself lives entirely in the two generalized resolvers,
 * shared with Product/Quote (App\Quotes\QuoteAttributeResolver, its model).
 *
 * N+1-free: `resolve()`/`layout()` rely on `quoteLines.product.category`
 * already being eager-loaded (WorkOrderService::DETAIL_RELATIONS); when it is
 * not, `loadMissing()` covers it in ONE query. `formContext()` loads every
 * referenced QuoteLine (with its product's category) in ONE query.
 */
final class WorkOrderAttributeResolver
{
    public function __construct(
        private readonly AttributeSetResolver $setResolver,
        private readonly AttributeLayoutMerger $layoutMerger,
    ) {}

    /**
     * @return Collection<int, ApplicableAttribute>
     */
    public function resolve(WorkOrder $workOrder): Collection
    {
        return $this->setResolver->resolve($this->categoryIdsForWorkOrder($workOrder), AttributeContext::WorkOrder);
    }

    /**
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function layout(WorkOrder $workOrder, FormMode $formMode): ?array
    {
        return $this->layoutMerger->resolve($this->categoryIdsForWorkOrder($workOrder), AttributeContext::WorkOrder, $formMode);
    }

    /**
     * The `POST /api/work-orders/form-context` shape (spec 0098, D-7): both
     * blocks, resolved once for the SAME category-id list — the not-yet-saved
     * `quote_line_ids` composed so far, be it the WorkOrder form or the
     * Contract's "Programma" dialog.
     *
     * @param  array<int, int>  $quoteLineIds
     * @return array{applicable_attributes: Collection<int, ApplicableAttribute>, attribute_layout: array<string, mixed>|null}
     */
    public function formContext(array $quoteLineIds, FormMode $formMode): array
    {
        $categoryIds = $this->categoryIdsForQuoteLineIds($quoteLineIds);

        return [
            'applicable_attributes' => $this->setResolver->resolve($categoryIds, AttributeContext::WorkOrder),
            'attribute_layout' => $this->layoutMerger->resolve($categoryIds, AttributeContext::WorkOrder, $formMode),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function categoryIdsForWorkOrder(WorkOrder $workOrder): array
    {
        $workOrder->loadMissing('quoteLines.product.category');

        return $workOrder->quoteLines
            ->pluck('product.category.id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $quoteLineIds
     * @return array<int, int>
     */
    private function categoryIdsForQuoteLineIds(array $quoteLineIds): array
    {
        if ($quoteLineIds === []) {
            return [];
        }

        return QuoteLine::query()
            ->whereIn('id', $quoteLineIds)
            ->with('product.category')
            ->get()
            ->pluck('product.category.id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
