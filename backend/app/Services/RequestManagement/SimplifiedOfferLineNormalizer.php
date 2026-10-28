<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\Quotes\QuoteLineData;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Support\Collection;

/**
 * Server-side freeze of an offer line's quantity/unit-price/VAT-rate on a
 * `simplified_offer_line` classification (spec 0114, D-4/D-5): the operator
 * picks a product, this is what overwrites whatever the client sent for the
 * other three fields — the client stays a preview, never the source of
 * truth, on every write channel of the module (creation, work panel, inline
 * cell, D-4). `product_categories.simplified_offer_line` is root-owned but
 * mirrored onto every descendant row server-side, so a category is checked
 * straight off its own column — no walk to the root at runtime.
 *
 * Two entry points for the module's two choke points: RequestCreationService
 * (every row is NEW, so a simplified classification freezes the whole set)
 * and RequestOfferLineWriter (a PATCH mixes new/changed/untouched rows, so
 * only the ones D-6 covers are frozen). The `$isNew || $productChanged`
 * discipline is the same one QuoteLineWriter::sync() already applies to
 * `unit_of_measure_id` (spec 0088, D-5) — replicated here rather than reused,
 * because QuoteLineWriter is shared with the Offerte module and stays
 * neutral on this rule (D-3): the Offerte endpoints never normalize a row,
 * even on a simplified category.
 */
final class SimplifiedOfferLineNormalizer
{
    /**
     * The created Offerta's REVENUE rows (RequestCreationService, before
     * QuoteService::create()): every row is new, so a simplified
     * classification freezes them all, unconditionally.
     *
     * @param  array<int, QuoteLineData>  $lines
     * @return array<int, QuoteLineData>
     */
    public function normalizeForCreation(Opportunity $opportunity, array $lines): array
    {
        if ($lines === [] || ! $this->isSimplified($opportunity)) {
            return $lines;
        }

        $products = $this->resolveProducts(array_map(
            static fn (QuoteLineData $line): int => $line->productId,
            $lines,
        ));

        $prices = $this->supplierPrices(array_map(
            static fn (QuoteLineData $line): int => $line->productId,
            $lines,
        ));

        return array_map(
            fn (QuoteLineData $line): QuoteLineData => $this->freezeLine($line, $products->get($line->productId), $prices),
            $lines,
        );
    }

    /**
     * The validated `offer_lines` rows of a PATCH — the work panel and the
     * inline grid cell both reach RequestOfferLineWriter::apply() with this
     * same raw shape, before it delegates to QuoteService::update(). A row is
     * re-frozen from its product when it carries no persisted `id`, or when
     * its `product_id` differs from what is persisted under that id (D-6);
     * an otherwise-untouched row is forced back onto its OWN persisted
     * values instead — the freeze is a server-side defense, not a client
     * convenience (spec 0114 constraints), so a resubmit can never smuggle
     * an arbitrary quantity/unit-price/VAT-rate through on a row the client
     * no longer even shows a control for.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function normalizeForUpdate(Quote $quote, array $rows): array
    {
        // Step 1: nothing to freeze on an empty submission or a classification
        // that never opted in — skip the persisted/product lookups entirely.
        if ($rows === [] || ! $this->isSimplified($this->resolveOpportunity($quote))) {
            return $rows;
        }

        // Step 2: the offer's PERSISTED rows, keyed by id, and the submitted
        // rows' products — each batched once, never a query per row.
        $persisted = $this->persistedLines($quote);
        $products = $this->resolveProducts(array_map(
            static fn (array $row): int => (int) $row['product_id'],
            $rows,
        ));

        $prices = $this->supplierPrices(array_map(
            static fn (array $row): int => (int) $row['product_id'],
            $rows,
        ));

        // Step 3: re-freeze the rows D-6 covers, force the rest back onto
        // their own persisted values.
        return array_map(
            fn (array $row): array => $this->freezeRow($row, $persisted, $products, $prices),
            $rows,
        );
    }

    /**
     * Whether at least one of $opportunity's covered categories carries the
     * root-owned setting — one batched query against the column already
     * mirrored onto every category row (spec 0114).
     */
    private function isSimplified(Opportunity $opportunity): bool
    {
        $categoryIds = $opportunity->productLines()->pluck('product_category_id')->all();

        if ($categoryIds === []) {
            return false;
        }

        return ProductCategory::query()
            ->whereIn('id', $categoryIds)
            ->where('simplified_offer_line', true)
            ->exists();
    }

    /**
     * @param  Collection<string, string>  $prices  supplier price keyed by "product_id:supplier_id"
     */
    private function freezeLine(QuoteLineData $line, ?Product $product, Collection $prices): QuoteLineData
    {
        return new QuoteLineData(
            productId: $line->productId,
            quantity: 1.0,
            unitPrice: (float) $this->frozenPrice($product, $line->productId, $line->supplierId, $prices),
            vatRateId: $product?->vat_rate_id !== null ? (int) $product->vat_rate_id : null,
            sortOrder: $line->sortOrder,
            id: $line->id,
            commissions: $line->commissions,
            supplierId: $line->supplierId,
            hasSupplier: $line->hasSupplier,
        );
    }

    /**
     * @param  Collection<int, QuoteLine>  $persisted  keyed by quote_line id
     * @param  Collection<int, Product>  $products
     * @param  Collection<string, string>  $prices  supplier price keyed by "product_id:supplier_id"
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function freezeRow(array $row, Collection $persisted, Collection $products, Collection $prices): array
    {
        $id = isset($row['id']) ? (int) $row['id'] : null;
        $productId = (int) $row['product_id'];
        $existing = $id === null ? null : $persisted->get($id);
        $isNew = $existing === null;
        $productChanged = ! $isNew && (int) $existing->product_id !== $productId;
        // Spec 0320, D-17: an omitted `supplier_id` on an existing row keeps the stored supplier.
        $supplierId = array_key_exists('supplier_id', $row) ? $this->intOrNull($row['supplier_id']) : $existing?->supplier_id;
        $supplierChanged = ! $isNew && $supplierId !== $existing->supplier_id;

        if (! $isNew && ! $productChanged && ! $supplierChanged) {
            $row['quantity'] = $existing->quantity;
            $row['unit_price'] = $existing->unit_price;
            $row['vat_rate_id'] = $existing->vat_rate_id;

            return $row;
        }

        $product = $products->get($productId);
        $row['quantity'] = 1;
        $row['unit_price'] = $this->frozenPrice($product, $productId, $supplierId, $prices);
        $row['vat_rate_id'] = $product?->vat_rate_id;

        return $row;
    }

    /**
     * The row's supplier price when that supplier is one of the product's,
     * else the standard price (a product without suppliers) — spec 0320, D-17.
     *
     * @param  Collection<string, string>  $prices
     */
    private function frozenPrice(?Product $product, int $productId, ?int $supplierId, Collection $prices): string|float|int
    {
        if ($supplierId !== null && $prices->has($productId.':'.$supplierId)) {
            return $prices->get($productId.':'.$supplierId);
        }

        return $product?->price ?? 0;
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * One batched read of the suppliers' prices for a set of products.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<string, string> keyed by "product_id:supplier_id"
     */
    private function supplierPrices(array $productIds): Collection
    {
        return ProductSupplier::query()
            ->whereIn('product_id', array_values(array_unique($productIds)))
            ->get(['product_id', 'supplier_id', 'price'])
            ->mapWithKeys(static fn (ProductSupplier $row): array => [$row->product_id.':'.$row->supplier_id => (string) $row->price]);
    }

    /**
     * @return Collection<int, QuoteLine> this offer's PERSISTED revenue rows, keyed by id
     */
    private function persistedLines(Quote $quote): Collection
    {
        return $quote->offerLines()->get(['id', 'product_id', 'supplier_id', 'quantity', 'unit_price', 'vat_rate_id'])->keyBy('id');
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     */
    private function resolveProducts(array $ids): Collection
    {
        $ids = array_values(array_unique($ids));

        return $ids === [] ? collect() : Product::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * Same "resolve a possibly-unloaded belongsTo" idiom RequestManagementService
     * uses (Model::preventLazyLoading() is active outside production): the
     * route-bound Quote this writer receives never eager-loads `opportunity`.
     */
    private function resolveOpportunity(Quote $quote): Opportunity
    {
        return $quote->relationLoaded('opportunity')
            ? $quote->opportunity
            : $quote->opportunity()->firstOrFail();
    }
}
