import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import { calculateCommissionTotals, calculateMarginBeforeCosts } from '@/features/quotes/commission-calculator'
import {
  computeQuoteTotals,
  EMPTY_QUOTE_TOTALS_SUMMARY,
  round2,
  type QuoteLineForTotals,
} from '@/features/quotes/quote-totals'
import type { QuoteFormValues, QuoteLineFormValues } from '@/features/quotes/quote-schema'

/**
 * Maps a row's nullable form values onto `quote-totals.ts`'s input shape,
 * defaulting an untouched field to 0 (mirrors the server's own handling of an
 * empty tab, AC-042). `vatRatePercentFor` resolves the row's own
 * `vat_rate_id` to a percentage from the shared cache (`useQuoteLineCaches`):
 * the picker itself never exposes one.
 */
function toLineForTotals(
  row: QuoteLineFormValues,
  vatRatePercentFor: (vatRateId: number) => number | null,
): QuoteLineForTotals {
  return {
    quantity: row.quantity ?? 0,
    unitPrice: row.unit_price ?? 0,
    vatRatePercent: row.vat_rate_id !== null ? vatRatePercentFor(row.vat_rate_id) : null,
  }
}

/**
 * The live economic totals of the lines being typed (AC-071): recomputed on
 * every `offer_lines`/`cost_lines` keystroke via `useWatch`, zero network
 * calls — `computeQuoteTotals` (D-9) is byte-for-byte the server's own
 * rounding rule (D-12). Shared by the live summary below the rows and the
 * create form's KPI strip, so the two never disagree.
 */
export function useQuoteLiveTotals(
  control: Control<QuoteFormValues>,
  vatRatePercentFor: (vatRateId: number) => number | null,
) {
  const offerLines = useWatch({ control, name: 'offer_lines' })
  const costLines = useWatch({ control, name: 'cost_lines' })

  // Spec 0145 D-1/D-9: the base of a PERCENTAGE commission is this row's own
  // net minus whatever cost the SAME `cost_lines` imputes to it.
  const commissionTotals = useMemo(
    () => calculateCommissionTotals(offerLines, costLines),
    [offerLines, costLines],
  )

  // Spec 0202 D-8: the live margin is the sum of every row's margin by its
  // frozen Supplier commission direction (RECEIVED: s - p; PAID: n - p - s;
  // none: n - p) minus ALL cost rows. `computeQuoteTotals` itself stays
  // commission-agnostic (D-5's revenue - cost, Ricavi attesi unchanged, D-9);
  // the margin is replaced here so the detail path (already net from the
  // server) never double-subtracts.
  const totals = useMemo(() => {
    if (offerLines.length === 0 && costLines.length === 0) {
      return EMPTY_QUOTE_TOTALS_SUMMARY
    }
    const base = computeQuoteTotals(
      offerLines.map((row) => toLineForTotals(row, vatRatePercentFor)),
      costLines.map((row) => toLineForTotals(row, vatRatePercentFor)),
    )
    return { ...base, margin: { net: round2(calculateMarginBeforeCosts(offerLines, costLines) - base.cost.net) } }
  }, [offerLines, costLines, vatRatePercentFor])

  return { offerLines, costLines, commissionTotals, totals }
}
