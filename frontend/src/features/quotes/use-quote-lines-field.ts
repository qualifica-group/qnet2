import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'

/** A freshly-added row: every field empty, matching the schema's nullable-per-field shape. */
const EMPTY_LINE_ROW: QuoteLineFormValues = {
  product_id: null,
  quantity: null,
  unit_price: null,
  vat_rate_id: null,
}

interface UseQuoteLinesFieldArgs {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  /** Which of the product's own prices precompiles `unit_price` (D-6): `price` on Offer rows, `cost` on Cost rows. */
  variant: 'revenue' | 'cost'
  /**
   * Feeds the shared VAT-percent cache (`use-quote-form.ts`) so the live
   * summary preview stays accurate the moment a product is picked
   * (AC-071/AC-074) — the `vat-rates/for-select` picker itself never exposes
   * a percentage, only the product's own `meta` does.
   */
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
}

/**
 * Owns one tab's (`offer_lines`/`cost_lines`) row array editing: add/remove
 * and the product-driven precompilation (AC-074) — mirrors
 * `useProductLinesField`'s "add empty row / edit in place" shape.
 */
export function useQuoteLinesField({ value, onChange, variant, rememberVatRatePercent }: UseQuoteLinesFieldArgs) {
  const addRow = () => onChange([...value, EMPTY_LINE_ROW])

  const removeRow = (index: number) => onChange(value.filter((_, rowIndex) => rowIndex !== index))

  const setField = (index: number, patch: Partial<QuoteLineFormValues>) =>
    onChange(value.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)))

  /**
   * Precompiles `unit_price`/`vat_rate_id` from the picked product's `meta`
   * (AC-074, D-6); clearing the product only clears its own id, leaving
   * quantity/price/rate exactly as the user left them.
   */
  const setProduct = (index: number, productId: number | null, item: QuoteProductForSelectItem | null) => {
    if (productId === null || !item) {
      setField(index, { product_id: null })
      return
    }

    const priceSource = variant === 'revenue' ? item.meta.price : item.meta.cost
    const unitPrice = priceSource !== null ? Number(priceSource) : null

    if (item.meta.vat_rate_id !== null && item.meta.vat_rate !== null) {
      rememberVatRatePercent(item.meta.vat_rate_id, Number(item.meta.vat_rate))
    }

    onChange(
      value.map((row, rowIndex) =>
        rowIndex === index
          ? { ...row, product_id: productId, unit_price: unitPrice, vat_rate_id: item.meta.vat_rate_id }
          : row,
      ),
    )
  }

  return { addRow, removeRow, setField, setProduct }
}
