/* eslint-disable react-refresh/only-export-components -- small formatting/mapping helpers (`formatQuoteAmount`/`totalsFromPersistedSummary`) shared by the row editor and the read-only detail view, colocated with the summary component they feed */
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { TrendingDown, TrendingUp, Wallet, type LucideIcon } from 'lucide-react'
import i18n from '@/i18n'
import { cn } from '@/lib/utils'
import {
  computeQuoteTotals,
  EMPTY_QUOTE_TOTALS_SUMMARY,
  type QuoteAmountAggregate,
  type QuoteLineForTotals,
  type QuoteTotalsSummary,
} from '@/features/quotes/quote-totals'
import type { QuoteFormValues, QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteSummary as QuoteSummaryData } from '@/features/quotes/types'

/**
 * Formats a plain, already-numeric amount using the active UI locale (mirrors
 * `features/products/column-renderers.ts`'s `formatDecimal`, minus the
 * unknown-input guard: every value reaching here is a real computed number,
 * never a raw server string).
 */
export function formatQuoteAmount(value: number): string {
  return new Intl.NumberFormat(i18n.language, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

/**
 * Maps a row's nullable form values onto `quote-totals.ts`'s input shape,
 * defaulting an untouched field to 0 (mirrors the server's own handling of an
 * empty tab, AC-042). `vatRatePercentFor` resolves the row's own
 * `vat_rate_id` to a percentage from the shared cache seeded by
 * `use-quote-form.ts` (the picker itself never exposes one).
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

/** Maps the persisted `QuoteSummary` (decimal strings, D-9) onto the numeric shape the presentational component renders. */
export function totalsFromPersistedSummary(summary: QuoteSummaryData): QuoteTotalsSummary {
  return {
    revenue: {
      net: Number(summary.revenue.net),
      vat: Number(summary.revenue.vat),
      gross: Number(summary.revenue.gross),
    },
    cost: {
      net: Number(summary.cost.net),
      vat: Number(summary.cost.vat),
      gross: Number(summary.cost.gross),
    },
    margin: { net: Number(summary.margin.net) },
  }
}

interface QuoteAmountBlockProps {
  icon: LucideIcon
  title: string
  aggregate: QuoteAmountAggregate
  netLabel: string
  vatLabel: string
  grossLabel: string
}

/** One net/vat/gross card of the summary (Ricavi Attesi or Costi Attesi, D-5). */
function QuoteAmountBlock({ icon: Icon, title, aggregate, netLabel, vatLabel, grossLabel }: QuoteAmountBlockProps) {
  return (
    <div className="flex flex-col gap-1.5 rounded-md border bg-card p-3">
      <div className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
        <Icon aria-hidden="true" className="size-3.5" />
        {title}
      </div>
      <dl className="flex flex-col gap-1 text-xs">
        <div className="flex items-center justify-between">
          <dt className="text-muted-foreground">{netLabel}</dt>
          <dd className="font-medium tabular-nums">{formatQuoteAmount(aggregate.net)}</dd>
        </div>
        <div className="flex items-center justify-between">
          <dt className="text-muted-foreground">{vatLabel}</dt>
          <dd className="font-medium tabular-nums">{formatQuoteAmount(aggregate.vat)}</dd>
        </div>
        <div className="flex items-center justify-between border-t pt-1">
          <dt className="text-muted-foreground">{grossLabel}</dt>
          <dd className="font-semibold tabular-nums">{formatQuoteAmount(aggregate.gross)}</dd>
        </div>
      </dl>
    </div>
  )
}

interface QuoteSummaryProps {
  totals: QuoteTotalsSummary
}

/**
 * Presentational three-block economic summary (D-5): Ricavi Attesi / Costi
 * Attesi / Margine Atteso, the margin computed on the imponibile only
 * (never clamped to zero, AC-043). Pure render — every number is precomputed
 * by the caller (`QuoteLiveSummary` for the live form preview,
 * `totalsFromPersistedSummary` for the read-only detail).
 */
export function QuoteSummary({ totals }: QuoteSummaryProps) {
  const { t } = useTranslation()
  const marginNegative = totals.margin.net < 0

  return (
    <div className="rounded-lg border bg-surface p-3">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <QuoteAmountBlock
          icon={TrendingUp}
          title={t('quotes.form.summary.revenue')}
          aggregate={totals.revenue}
          netLabel={t('quotes.form.summary.net')}
          vatLabel={t('quotes.form.summary.vat')}
          grossLabel={t('quotes.form.summary.gross')}
        />
        <QuoteAmountBlock
          icon={TrendingDown}
          title={t('quotes.form.summary.cost')}
          aggregate={totals.cost}
          netLabel={t('quotes.form.summary.net')}
          vatLabel={t('quotes.form.summary.vat')}
          grossLabel={t('quotes.form.summary.gross')}
        />
        <div className="flex flex-col gap-1.5 rounded-md border bg-card p-3">
          <div className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
            <Wallet aria-hidden="true" className="size-3.5" />
            {t('quotes.form.summary.margin')}
          </div>
          <p
            className={cn(
              'text-lg font-semibold tabular-nums',
              marginNegative ? 'text-destructive' : 'text-foreground',
            )}
          >
            {formatQuoteAmount(totals.margin.net)}
          </p>
          <p className="text-[11px] text-muted-foreground">{t('quotes.form.summary.marginHint')}</p>
        </div>
      </div>
    </div>
  )
}

interface QuoteLiveSummaryProps {
  control: Control<QuoteFormValues>
  vatRatePercentFor: (vatRateId: number) => number | null
}

/**
 * Live client-side preview (AC-071): recomputes on every `offer_lines`/
 * `cost_lines` keystroke via `useWatch`, zero network calls —
 * `computeQuoteTotals`/`EMPTY_QUOTE_TOTALS_SUMMARY` from `quote-totals.ts`
 * (D-9) are the only math involved, byte-for-byte the server's own rounding
 * rule (D-12). Mounted as a sibling of the form's `<Tabs>`, never inside a
 * `TabsContent`, so it stays visible regardless of the active tab (AC-070).
 */
export function QuoteLiveSummary({ control, vatRatePercentFor }: QuoteLiveSummaryProps) {
  const offerLines = useWatch({ control, name: 'offer_lines' })
  const costLines = useWatch({ control, name: 'cost_lines' })

  const totals = useMemo(() => {
    if (offerLines.length === 0 && costLines.length === 0) {
      return EMPTY_QUOTE_TOTALS_SUMMARY
    }
    return computeQuoteTotals(
      offerLines.map((row) => toLineForTotals(row, vatRatePercentFor)),
      costLines.map((row) => toLineForTotals(row, vatRatePercentFor)),
    )
  }, [offerLines, costLines, vatRatePercentFor])

  return <QuoteSummary totals={totals} />
}
