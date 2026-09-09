import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'

/** Which of the product's two prices the tab precompiles from (D-6). */
export type QuoteLineVariant = 'revenue' | 'cost'

/** A freshly-added row: every field empty, matching the schema's nullable-per-field shape. */
export const EMPTY_LINE_ROW: QuoteLineFormValues = {
  product_id: null,
  quantity: null,
  // Filled from the picked product's `meta` as a preview; congelated
  // server-side on save (spec 0088, D-5).
  unit_of_measure: null,
  unit_price: null,
  vat_rate_id: null,
  commissions: [],
}

/**
 * The quantity a row opens on as soon as it carries a product, wherever the
 * product lands on it: manual pick (`setProduct` below), deep-link seeding
 * (`quote-form-body.tsx`) and the mono-product autofill of Gestione Richieste
 * (`use-offer-lines-autofill.ts`). Both the schema (`quote-schema.ts`) and the
 * backend (`QuoteLineRules`, `gt:0`) reject an empty quantity AND 0, so a
 * picked row left empty simply blocks the save until the operator types the
 * only value that is ever the starting point (user directive 2026-09-09).
 */
export const DEFAULT_LINE_QUANTITY = 1

interface UseQuoteLinesFieldArgs {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  /** Which of the product's own prices precompiles `unit_price` (D-6): `price` on Offer rows, `cost` on Cost rows. */
  variant: QuoteLineVariant
  /**
   * Feeds the shared VAT-percent cache (`use-quote-form.ts`) so the live
   * summary preview stays accurate the moment a product is picked
   * (AC-071/AC-074) — the `vat-rates/for-select` picker itself never exposes
   * a percentage, only the product's own `meta` does.
   */
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  /**
   * Feeds the shared product -> typology cache (`use-quote-form.ts`) so the
   * live per-typology summary (spec 0099) buckets the row the moment a
   * product is picked. Optional: Gestione Richieste mounts this editor
   * without that summary.
   */
  rememberProductTypology?: (productId: number, typologyId: number) => void
}

/**
 * The row values a picked product precompiles (AC-074, D-6): the price of the
 * tab's own variant plus the product's VAT rate. Pure, so the deep-link
 * seeding path (an offer opened with products already chosen, user directive
 * 2026-08-31) fills its rows exactly like a manual pick does.
 */
export function lineValuesFromProduct(
  item: QuoteProductForSelectItem,
  variant: QuoteLineVariant,
): Pick<QuoteLineFormValues, 'product_id' | 'unit_of_measure' | 'unit_price' | 'vat_rate_id'> {
  const priceSource = variant === 'revenue' ? item.meta.price : item.meta.cost

  return {
    product_id: item.id,
    // Display-only preview of what the save will congelate on the line
    // (spec 0088, D-5): without it the row's unit column stays "—" for the
    // whole create/edit, even though the product always carries a unit.
    unit_of_measure: item.meta.unit_of_measure,
    unit_price: priceSource !== null ? Number(priceSource) : null,
    vat_rate_id: item.meta.vat_rate_id,
  }
}

/**
 * Owns one tab's (`offer_lines`/`cost_lines`) row array editing: add/remove
 * and the product-driven precompilation (AC-074) — mirrors
 * `useProductLinesField`'s "add empty row / edit in place" shape.
 */
export function useQuoteLinesField({ value, onChange, variant, rememberVatRatePercent, rememberProductTypology }: UseQuoteLinesFieldArgs) {
  const addRow = () => onChange([...value, EMPTY_LINE_ROW])

  const removeRow = (index: number) => onChange(value.filter((_, rowIndex) => rowIndex !== index))

  const setField = (index: number, patch: Partial<QuoteLineFormValues>) =>
    onChange(value.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)))

  /**
   * Precompiles `unit_price`/`vat_rate_id`/`unit_of_measure` from the picked product's `meta`
   * (AC-074, D-6) and opens an empty quantity on `DEFAULT_LINE_QUANTITY`;
   * clearing the product only clears its own id, leaving quantity/price/rate
   * exactly as the user left them.
   */
  const setProduct = (
    index: number,
    productId: number | null,
    item: QuoteProductForSelectItem | null,
    commissions?: QuoteLineFormValues['commissions'],
  ) => {
    if (productId === null || !item) {
      setField(index, { product_id: null, unit_of_measure: null })
      return
    }

    if (item.meta.vat_rate_id !== null && item.meta.vat_rate !== null) {
      rememberVatRatePercent(item.meta.vat_rate_id, Number(item.meta.vat_rate))
    }

    // Spec 0099: the picked product's typology, for the live summary's buckets.
    if (item.meta.product_typology) {
      rememberProductTypology?.(item.id, item.meta.product_typology.id)
    }

    onChange(
      value.map((row, rowIndex) =>
        rowIndex === index
          ? {
              ...row,
              ...lineValuesFromProduct(item, variant),
              // A quantity the operator has already typed is never
              // overwritten: only an empty row gets the default.
              ...(row.quantity === null ? { quantity: DEFAULT_LINE_QUANTITY } : {}),
              ...(commissions ? { commissions } : {}),
            }
          : row,
      ),
    )
  }

  return { addRow, removeRow, setField, setProduct }
}
