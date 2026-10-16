import { useMemo, useState } from 'react'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { aggregateLines } from '@/features/quotes/quote-totals'
import { isPristineLineRow } from '@/features/quotes/quote-line-values'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine } from '@/features/quotes/types'
import { useQuoteLinesField } from '@/features/quotes/use-quote-lines-field'
import { seedSoleProductRows } from '@/features/request-management/use-offer-lines-autofill'
import type { OfferCatalogProduct, OfferLineProductView } from './types'

interface UseOfferLinesEditorArgs {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  /** The persisted rows, for the product views of rows not picked in this session. `[]` on create. */
  knownLines: QuoteLine[]
  /** Spec 0077: `1` under a `single` category, `MAX_LINES_PER_TAB` otherwise. */
  maxRows: number
  /** Spec 0114: picks congeal quantity/price/VAT exactly as the server does. */
  simplified: boolean
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
}

/** A row the editor renders, with its position in the form array (errors are indexed on it, D-7). */
export interface VisibleOfferLine {
  row: QuoteLineFormValues
  index: number
}

function viewFromLine(line: QuoteLine): OfferLineProductView {
  return {
    id: line.product.id,
    name: line.product.name,
    code: line.product.code,
    categoryId: line.product.category?.id ?? null,
    typology: line.product.product_typology,
  }
}

function viewFromProduct(item: OfferCatalogProduct): OfferLineProductView {
  return {
    id: item.id,
    name: item.label,
    code: item.meta.code,
    categoryId: item.meta.category_id,
    typology: item.meta.product_typology,
  }
}

/**
 * Row logic of the "catalogo + righe" editor (spec 0268). Reuses the Offerte
 * row primitives instead of re-implementing them: `seedSoleProductRows` for a
 * multi-pick (fills pristine rows first, skips products already on the card,
 * caps at `maxRows` — D-3/D-7) and `useQuoteLinesField` for in-place edits and
 * the `single`-mode replacement (D-6), so a catalogue pick precompiles a row
 * exactly as the old per-row picker did.
 */
export function useOfferLinesEditor({
  value,
  onChange,
  knownLines,
  maxRows,
  simplified,
  vatRatePercentFor,
  rememberVatRatePercent,
}: UseOfferLinesEditorArgs) {
  const { removeRow, setField, setProduct } = useQuoteLinesField({
    value,
    onChange,
    variant: 'revenue',
    rememberVatRatePercent,
  })
  const [pickedViews, setPickedViews] = useState<Map<number, OfferLineProductView>>(() => new Map())

  const visibleLines = useMemo<VisibleOfferLine[]>(
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
  const fetchedProducts = useForSelectLabels({ resource: PRODUCTS_FOR_SELECT_RESOURCE, ids: unresolvedIds })

  const productViews = useMemo(() => {
    const views = new Map(localViews)
    unresolvedIds.forEach((id) => {
      const item = fetchedProducts.get(id)
      if (item !== undefined) {
        views.set(id, viewFromProduct(item as OfferCatalogProduct))
      }
    })
    return views
  }, [localViews, unresolvedIds, fetchedProducts])

  const addedProductIds = useMemo(
    () => new Set(visibleLines.flatMap(({ row }) => (row.product_id === null ? [] : [row.product_id]))),
    [visibleLines],
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

  const remember = (items: OfferCatalogProduct[]) => {
    setPickedViews((previous) => {
      const next = new Map(previous)
      items.forEach((item) => next.set(item.id, viewFromProduct(item)))
      return next
    })
  }

  /** Multi-pick: one new row per product, at quantity 1 (spec 0268 AC-005). */
  const addProducts = (items: OfferCatalogProduct[]) => {
    // Step 1: the preview caches the rows read (VAT percent, product label).
    items.forEach((item) => {
      if (item.meta.vat_rate_id !== null && item.meta.vat_rate !== null) {
        rememberVatRatePercent(item.meta.vat_rate_id, Number(item.meta.vat_rate))
      }
    })
    remember(items)
    // Step 2: the rows themselves, through the autofill's own seeding rules.
    const next = seedSoleProductRows(value, items, maxRows, simplified)
    if (next !== value) {
      onChange(next)
    }
  }

  /** `single` mode (D-6): the pick replaces the product of the card's one row, or creates it. */
  const pickSingle = (item: OfferCatalogProduct) => {
    const index = value.findIndex((row) => !isPristineLineRow(row))
    if (index === -1) {
      addProducts([item])
      return
    }
    remember([item])
    setProduct(index, item.id, item, undefined, simplified)
  }

  return {
    visibleLines,
    productViews,
    addedProductIds,
    remainingSlots: Math.max(0, maxRows - visibleLines.length),
    totals,
    addProducts,
    pickSingle,
    removeRow,
    setField,
  }
}
