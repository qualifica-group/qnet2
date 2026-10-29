<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\ProformaRequestKind;
use App\Enums\QuoteLineType;
use App\Models\InvoiceLine;
use App\Models\ProformaRequest;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * The work-order lines a proforma request bills (spec 0288, D-3): the single
 * source shared by the issue modal and the save-time line guard. Revenue lines
 * whose typology has the request kind; consultancy keeps to the request offer,
 * institution spans the offers of the same issuing company and keeps to the
 * request supplier, the line's (spec 0320, D-8; null matches null).
 */
final class ProformaRequestBillableLines
{
    /**
     * Billable lines not yet in any document, with what the draft renders.
     *
     * @return Collection<int, QuoteLine>
     */
    public function available(ProformaRequest $request): Collection
    {
        return $this->select($request, fn ($query) => $query
            ->whereNotIn('quote_lines.id', InvoiceLine::query()->whereNotNull('quote_line_id')->select('quote_line_id'))
            ->with('product.vatRate:id,name,rate', 'vatRate:id,name,rate'));
    }

    /**
     * Ids of every line the request may bill, invoiced or not.
     *
     * @return array<int, int>
     */
    public function ids(ProformaRequest $request): array
    {
        return $this->select($request, fn ($query) => $query)->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    /**
     * @param  callable(BelongsToMany<QuoteLine, WorkOrder>): BelongsToMany<QuoteLine, WorkOrder>  $refine
     * @return Collection<int, QuoteLine>
     */
    private function select(ProformaRequest $request, callable $refine): Collection
    {
        $request->loadMissing('workOrder:id', 'quote:id,company_id');
        $isInstitution = $request->kind === ProformaRequestKind::Institution;

        // Step 1: revenue lines of the work order, within the request offer or the offers of its company
        $query = $request->workOrder->quoteLines()
            ->where('line_type', QuoteLineType::Revenue->value)
            ->with('product:id,name,product_typology_id,category_id,vat_rate_id', 'product.productTypology:id,proforma_kind')
            ->when(! $isInstitution, fn ($query) => $query->where('quote_lines.quote_id', $request->quote_id))
            ->when($isInstitution, fn ($query) => $query->whereHas('quote', fn (Builder $quote) => $quote->where('company_id', $request->quote->company_id)));

        // Step 2: keep the request kind and, for institution, its supplier
        return $refine($query)->get()
            ->filter(fn (QuoteLine $line): bool => $line->product?->productTypology?->proforma_kind === $request->kind)
            ->filter(fn (QuoteLine $line): bool => ! $isInstitution || $line->supplier_id === $request->supplier_id)
            ->values();
    }
}
