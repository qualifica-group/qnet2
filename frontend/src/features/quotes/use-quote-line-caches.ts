import { useCallback, useState } from 'react'
import {
  productNamesFromLines,
  productTypologyIdsFromLines,
  vatRatePercentsFromLines,
} from '@/features/quotes/quote-line-values'
import type { QuoteDetail } from '@/features/quotes/types'

/**
 * A remembered `id -> value` lookup, seeded once and topped up on every pick.
 * The setter keeps the map's identity when nothing changes, so the lookup —
 * and every memo depending on it — stays stable.
 */
function useLookup<TValue>(seed: () => Record<number, TValue>) {
  const [byId, setById] = useState<Record<number, TValue>>(seed)
  const remember = useCallback((id: number, value: TValue) => {
    setById((previous) => (previous[id] === value ? previous : { ...previous, [id]: value }))
  }, [])
  const lookup = useCallback((id: number): TValue | null => byId[id] ?? null, [byId])
  return [lookup, remember] as const
}

/**
 * The three client-side caches the line editors and the live summary read
 * (extracted from `useQuoteForm`, engineering.md §6), each seeded from the
 * persisted rows of the offer being edited (`null` on create) and topped up on
 * every product/VAT pick:
 *
 * - VAT percent: the `vat-rates/for-select` picker never exposes one, and the
 *   live preview (AC-071) must be byte-exact from the first render.
 * - product typology (spec 0099, D-6: only revenue lines feed the
 *   per-typology summary): the row itself carries no typology (D-5).
 * - product name (spec 0144): the Cost tab's "Associated product" options and
 *   the live per-product margin block need a label a row never carries.
 */
export function useQuoteLineCaches(quote: QuoteDetail | null) {
  const [vatRatePercentFor, rememberVatRatePercent] = useLookup<number>(() =>
    quote ? vatRatePercentsFromLines([...quote.offer_lines, ...quote.cost_lines]) : {},
  )
  const [productTypologyIdFor, rememberProductTypology] = useLookup<number>(() =>
    quote ? productTypologyIdsFromLines(quote.offer_lines) : {},
  )
  const [productNameFor, rememberProductName] = useLookup<string>(() =>
    quote ? productNamesFromLines(quote.offer_lines) : {},
  )

  return {
    vatRatePercentFor,
    rememberVatRatePercent,
    productTypologyIdFor,
    rememberProductTypology,
    productNameFor,
    rememberProductName,
  }
}

/** What the line editors and the live summary receive from the form hook. */
export type QuoteLineCaches = ReturnType<typeof useQuoteLineCaches>
