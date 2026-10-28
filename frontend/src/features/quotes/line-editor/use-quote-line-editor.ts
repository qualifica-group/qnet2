import { useMemo, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { productSuppliersOf, type ProductSupplierOption } from '@/features/products/product-suppliers'
import { commissionedDefaultProductIds, loadSupplierCommissions } from '@/features/products/supplier-commissions-api'
import { isPristineLineRow } from '@/features/quotes/quote-line-values'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { aggregateLines } from '@/features/quotes/quote-totals'
import type { QuoteLine } from '@/features/quotes/types'
import { appendProductRows, useQuoteLinesField, type QuoteLineVariant } from '@/features/quotes/use-quote-lines-field'
import type { QuoteCatalogProduct, QuoteLineCommissionsAdapter, QuoteLineProductView, VisibleQuoteLine } from './types'

interface UseQuoteLineEditorArgs {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  variant: QuoteLineVariant
  /** The persisted rows, for the product views of rows not picked in this session. `[]` on create. */
  knownLines: QuoteLine[]
  /** Spec 0077: `1` under a `single` category, `MAX_LINES_PER_TAB` otherwise. */
  maxRows: number
  /** Spec 0114: picks congeal quantity/price/VAT exactly as the server does. */
  simplified: boolean
  /** Spec 0269 D-3: cost rows may repeat a product. */
  allowDuplicates: boolean
  /** Revenue rows of the Offerte form: the commission defaults each pick brings. */
  commissions?: QuoteLineCommissionsAdapter
  /** Feeds the channel's own caches (typology summary, product names) with every picked product. */
  onProductsPicked?: (items: QuoteCatalogProduct[]) => void
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
}

function viewFromLine(line: QuoteLine): QuoteLineProductView {
  return {
    id: line.product.id,
    name: line.product.name,
    code: line.product.code,
    categoryId: line.product.category?.id ?? null,
    typology: line.product.product_typology,
  }
}

function viewFromProduct(item: QuoteCatalogProduct): QuoteLineProductView {
  return {
    id: item.id,
    name: item.label,
    code: item.meta.code,
    categoryId: item.meta.category_id,
    typology: item.meta.product_typology,
  }
}

/**
 * Row logic of the "catalogo + righe" editor (spec 0268, shared by spec
 * 0269). Reuses the Offerte row primitives instead of re-implementing them:
 * `appendProductRows` for a multi-pick (pristine rows first, duplicates per
 * channel, `maxRows`) and `useQuoteLinesField` for in-place edits and the
 * `single`-mode replacement, so a catalogue pick precompiles a row exactly as
 * the old per-row picker did.
 */
export function useQuoteLineEditor({
  value,
  onChange,
  variant,
  knownLines,
  maxRows,
  simplified,
  allowDuplicates,
  commissions,
  onProductsPicked,
  vatRatePercentFor,
  rememberVatRatePercent,
}: UseQuoteLineEditorArgs) {
  const queryClient = useQueryClient()
  const { removeRow, setField, setProduct, setSupplier } = useQuoteLinesField({ value, onChange, variant, rememberVatRatePercent })
  const [pickedViews, setPickedViews] = useState<Map<number, QuoteLineProductView>>(() => new Map())

  const visibleLines = useMemo<VisibleQuoteLine[]>(
    () => value.flatMap((row, index) => (isPristineLineRow(row) ? [] : [{ row, index }])),
    [value],
  )

  const localViews = useMemo(() => {
    const views = new Map(knownLines.map((line) => [line.product.id, viewFromLine(line)]))
    pickedViews.forEach((view, id) => views.set(id, view))
    return views
  }, [knownLines, pickedViews])

  // A row filled outside the catalogue (the mono-product autofill) carries an
  // id only: its label comes from the same ids-keyed lookup the pickers use.
  const unresolvedIds = useMemo(
    () => [
      ...new Set(
        visibleLines.flatMap(({ row }) =>
          row.product_id === null || localViews.has(row.product_id) ? [] : [row.product_id],
        ),
      ),
    ],
    [visibleLines, localViews],
  )
  // Spec 0320: a persisted row carries its supplier but not the product's
  // options, so its product is looked up too (the same ids-keyed query).
  const supplierLookupIds = useMemo(
    () => [
      ...new Set(
        visibleLines.flatMap(({ row }) =>
          row.product_id === null || row.product_suppliers !== undefined ? [] : [row.product_id],
        ),
      ),
    ],
    [visibleLines],
  )
  const lookupIds = useMemo(() => [...new Set([...unresolvedIds, ...supplierLookupIds])], [unresolvedIds, supplierLookupIds])
  const fetchedProducts = useForSelectLabels({ resource: PRODUCTS_FOR_SELECT_RESOURCE, ids: lookupIds })

  const productViews = useMemo(() => {
    const views = new Map(localViews)
    unresolvedIds.forEach((id) => {
      const item = fetchedProducts.get(id)
      if (item !== undefined) {
        views.set(id, viewFromProduct(item as QuoteCatalogProduct))
      }
    })
    return views
  }, [localViews, unresolvedIds, fetchedProducts])

  /** The options of a row's supplier select: its pick's own list, else the looked-up product's. */
  const suppliersOf = (row: QuoteLineFormValues): ProductSupplierOption[] => {
    if (row.product_suppliers !== undefined) {
      return row.product_suppliers
    }
    return row.product_id === null ? [] : productSuppliersOf((fetchedProducts.get(row.product_id) as QuoteCatalogProduct | undefined)?.meta)
  }

  // Products the catalogue marks "Gia' aggiunto": none where the channel allows repeats.
  const addedProductIds = useMemo(
    () =>
      allowDuplicates
        ? new Set<number>()
        : new Set(visibleLines.flatMap(({ row }) => (row.product_id === null ? [] : [row.product_id]))),
    [visibleLines, allowDuplicates],
  )

  const totals = useMemo(
    () =>
      aggregateLines(
        visibleLines.map(({ row }) => ({
          quantity: row.quantity ?? 0,
          unitPrice: row.unit_price ?? 0,
          vatRatePercent: row.vat_rate_id === null ? null : vatRatePercentFor(row.vat_rate_id),
        })),
      ),
    [visibleLines, vatRatePercentFor],
  )

  const remember = (items: QuoteCatalogProduct[]) => {
    setPickedViews((previous) => {
      const next = new Map(previous)
      items.forEach((item) => next.set(item.id, viewFromProduct(item)))
      return next
    })
    onProductsPicked?.(items)
  }

  /** Multi-pick: one new row per product, at quantity 1. `false` = nothing added (commission defaults failed). */
  const addProducts = async (items: QuoteCatalogProduct[]): Promise<boolean> => {
    // Step 1: the commission defaults, all or nothing (spec 0269 D-6).
    const defaults = commissions ? await commissions.defaultsForNewRows(items) : undefined
    if (defaults === null) {
      return false
    }
    // Step 1b: a cost row of a commissioned default supplier is free of charge (rev.3 D-23).
    const freeOfCharge = variant === 'cost' ? await commissionedDefaultProductIds(queryClient, items) : new Set<number>()
    // Step 2: the preview caches the rows read (VAT percent, product label, channel caches).
    items.forEach((item) => {
      if (item.meta.vat_rate_id !== null && item.meta.vat_rate !== null) {
        rememberVatRatePercent(item.meta.vat_rate_id, Number(item.meta.vat_rate))
      }
    })
    remember(items)
    // Step 3: the rows themselves.
    const next = appendProductRows(value, items, {
      maxRows,
      variant,
      simplified,
      allowDuplicates,
      extraFor: (product) => {
        const productDefaults = defaults?.get(product.id)
        return {
          ...(productDefaults
            ? {
                commissions: productDefaults.commissions,
                supplier_commission_direction: productDefaults.supplierCommissionDirection,
              }
            : {}),
          ...(freeOfCharge.has(product.id) ? { unit_price: 0 } : {}),
        }
      },
    })
    if (next !== value) {
      onChange(next)
    }
    return true
  }

  /** `single` mode: the pick replaces the product of the card's one row (confirmed when it carries commissions), or creates it. */
  const pickSingle = async (item: QuoteCatalogProduct) => {
    const index = value.findIndex((row) => !isPristineLineRow(row))
    if (index === -1) {
      await addProducts([item])
      return
    }
    const defaults = commissions ? await commissions.defaultsForReplacement(value[index], item) : undefined
    if (defaults === null) {
      return
    }
    remember([item])
    setProduct(index, item.id, item, defaults, simplified)
  }

  /**
   * Spec 0320 D-4/D-7: a supplier pick on a row. A revenue row first asks the
   * commission defaults of the new supplier (nothing changes if that is
   * declined or fails); a cost row reprices from the supplier's cost, or to 0
   * when that supplier earns a commission (rev.3 D-23).
   */
  const changeSupplier = async (index: number, option: ProductSupplierOption) => {
    const row = value[index]
    const defaults =
      variant === 'revenue' && commissions ? await commissions.defaultsForSupplier(row, option.supplier_id) : undefined
    if (defaults === null) {
      return
    }
    const commissioned =
      variant === 'cost'
        ? await loadSupplierCommissions(queryClient, {
            productId: row.product_id,
            categoryId: null,
            supplierIds: suppliersOf(row).map((candidate) => candidate.supplier_id),
          })
        : null
    setSupplier(index, option, defaults, !!commissioned?.get(option.supplier_id))
  }

  return {
    visibleLines,
    productViews,
    addedProductIds,
    remainingSlots: Math.max(0, maxRows - visibleLines.length),
    totals,
    addProducts,
    pickSingle,
    suppliersOf,
    changeSupplier,
    removeRow,
    setField,
  }
}
