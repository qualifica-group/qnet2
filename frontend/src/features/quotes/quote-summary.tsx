/* eslint-disable react-refresh/only-export-components -- small formatting/mapping helpers (`formatQuoteAmount`/`totalsFromPersistedSummary`) shared by the row editor and the read-only detail view, colocated with the summary component they feed */
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { HandCoins, Shapes, TrendingDown, TrendingUp, Wallet, type LucideIcon } from 'lucide-react'
import i18n from '@/i18n'
import { cn } from '@/lib/utils'
import { round2, type QuoteAmountAggregate, type QuoteTotalsSummary } from '@/features/quotes/quote-totals'
import {
  computeProductMargins,
  costLinesFromFormCostLines,
  productLinesFromFormOfferLines,
} from '@/features/quotes/quote-product-margins-calc'
import { QuoteProductMargins } from '@/features/quotes/quote-product-margins'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteSummary as QuoteSummaryData, QuoteTypologyTotal } from '@/features/quotes/types'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import type { ProductTypologyForSelectItem } from '@/features/product-typologies/for-select-api'
import { useQuoteLiveTotals } from '@/features/quotes/use-quote-live-totals'
import type { CommissionTotals } from './commission-calculator'

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

/** One bucket of the per-typology summary, as the card renders it (spec 0099). */
export interface QuoteTypologyBucket {
  id: number
  name: string
  color: string
  net: number
}

/** Hoisted: an inline literal would be a new reference on every render. */
const EMPTY_TYPOLOGY_BUCKETS: QuoteTypologyBucket[] = []

/** Maps the persisted `product_typologies` block (decimal strings) onto the card's numeric shape. */
export function typologyBucketsFromPersistedSummary(
  summary: QuoteSummaryData,
): QuoteTypologyBucket[] {
  return (summary.product_typologies ?? []).map((entry: QuoteTypologyTotal) => ({
    id: entry.id,
    name: entry.name,
    color: entry.color,
    net: Number(entry.net),
  }))
}

/**
 * The "Riepilogo per Tipologia Prodotto" card (spec 0099, D-6/D-7): every
 * CONFIGURED typology with the imponibile of the offer's revenue lines that
 * carry it — zero-filled, so a typology with no line here still shows at 0,00
 * (AC-051). Never hardcodes a typology: the list is whatever the caller
 * resolved from the module (AC-053/AC-054). Scrolls internally rather than
 * stretching the summary band when many typologies are configured (AC-062).
 */
function QuoteTypologyBlock({ buckets }: { buckets: QuoteTypologyBucket[] }) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-1.5 rounded-md border bg-card p-3">
      <div className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
        <Shapes aria-hidden="true" className="size-3.5" />
        {t('quotes.form.summary.productTypologies')}
      </div>
      {buckets.length === 0 ? (
        <p className="text-[11px] text-muted-foreground">
          {t('quotes.form.summary.noProductTypologies')}
        </p>
      ) : (
        <dl className="grid max-h-32 grid-cols-[minmax(0,1fr)] gap-1 overflow-y-auto text-xs">
          {buckets.map((bucket) => (
            <div key={bucket.id} className="flex items-center justify-between gap-2">
              <dt className="min-w-0">
                <ProductTypologyBadge name={bucket.name} color={bucket.color} className="max-w-full justify-start truncate" />
              </dt>
              <dd className="shrink-0 font-medium tabular-nums">{formatQuoteAmount(bucket.net)}</dd>
            </div>
          ))}
        </dl>
      )}
    </div>
  )
}

interface QuoteSummaryProps {
  totals: QuoteTotalsSummary
  commissionTotals?: CommissionTotals
  typologyBuckets?: QuoteTypologyBucket[]
}

/**
 * Presentational economic summary (D-5, spec 0145 D-8): Ricavi Attesi / Costi
 * Attesi / Riepilogo Commissioni / Margine Atteso / Riepilogo per Tipologia,
 * IN THIS ORDER — the margin sits right after the commissions it nets out
 * (D-3), never clamped to zero (AC-043). Pure render — every number is
 * precomputed by the caller (`QuoteLiveSummary` for the live form preview,
 * `totalsFromPersistedSummary` for the read-only detail).
 */
export function QuoteSummary({
  totals,
  commissionTotals = { commercial: 0, reporter: 0, supervisor: 0, supplier: 0 },
  typologyBuckets = EMPTY_TYPOLOGY_BUCKETS,
}: QuoteSummaryProps) {
  const { t } = useTranslation()
  const marginNegative = totals.margin.net < 0

  return (
    <div className="rounded-lg border bg-surface p-3">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
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
            <HandCoins aria-hidden="true" className="size-3.5" />
            {t('quotes.form.summary.commissions')}
          </div>
          <dl className="grid gap-1 text-xs">
            {(['commercial', 'reporter', 'supervisor', 'supplier'] as const).map((role) => (
              <div key={role} className="flex items-center justify-between">
                <dt className="text-muted-foreground">{t(`quotes.form.summary.roles.${role}`)}</dt>
                <dd className="font-medium tabular-nums">{formatQuoteAmount(commissionTotals[role])}</dd>
              </div>
            ))}
          </dl>
        </div>
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
        <QuoteTypologyBlock buckets={typologyBuckets} />
      </div>
    </div>
  )
}

interface QuoteLiveSummaryProps {
  control: Control<QuoteFormValues>
  vatRatePercentFor: (vatRateId: number) => number | null
  /**
   * Spec 0099: resolves a picked product's typology id from the shared cache
   * (`use-quote-form.ts`) — the row itself carries no typology (D-5).
   */
  productTypologyIdFor: (productId: number) => number | null
  /**
   * The configured typology catalogue (D-7), fetched by the CALLER — this
   * component stays a pure `useWatch` recompute with no data fetching of its
   * own (frontend.md §6), so the preview itself still costs zero requests per
   * keystroke (AC-060). Empty renders the block's own empty state.
   */
  typologyOptions?: ProductTypologyForSelectItem[]
  /**
   * Spec 0144: resolves a picked product's name for the "Margine per
   * prodotto" block — the row itself carries only `product_id` (D-5-like).
   */
  productNameFor?: (productId: number) => string | null
}

/** Hoisted: an inline `[]` default would be a new reference on every render. */
const NO_TYPOLOGY_OPTIONS: ProductTypologyForSelectItem[] = []
/** Hoisted: an inline function default would be a new reference on every render. */
const NO_PRODUCT_NAME = (): string | null => null

/**
 * Live client-side preview (AC-071): the totals of `useQuoteLiveTotals`,
 * recomputed on every keystroke with zero network calls, plus the
 * per-typology and per-product breakdowns of the same rows. Mounted as a
 * sibling of the rows' `<Tabs>`, never inside a `TabsContent`, so it stays
 * visible regardless of the active tab (AC-070).
 */
export function QuoteLiveSummary({
  control,
  vatRatePercentFor,
  productTypologyIdFor,
  typologyOptions = NO_TYPOLOGY_OPTIONS,
  productNameFor = NO_PRODUCT_NAME,
}: QuoteLiveSummaryProps) {
  const { offerLines, costLines, commissionTotals, totals } = useQuoteLiveTotals(control, vatRatePercentFor)
  const { field: fieldPermission } = useResourcePermissions()

  // Spec 0099, D-6: the SAME arithmetic the buckets' server-side counterpart
  // uses — each revenue row's already-rounded net (`quote-totals.ts`), summed
  // per typology, so the preview and the persisted summary never disagree.
  const typologyBuckets = useMemo<QuoteTypologyBucket[]>(() => {
    const netByTypologyId = new Map<number, number>()

    for (const row of offerLines) {
      if (row.product_id === null) {
        continue
      }
      const typologyId = productTypologyIdFor(row.product_id)
      if (typologyId === null) {
        continue
      }
      const net = round2((row.quantity ?? 0) * (row.unit_price ?? 0))
      netByTypologyId.set(typologyId, (netByTypologyId.get(typologyId) ?? 0) + net)
    }

    return typologyOptions.map((option) => ({
      id: option.id,
      name: option.label,
      color: option.meta.color,
      net: round2(netByTypologyId.get(option.id) ?? 0),
    }))
  }, [offerLines, productTypologyIdFor, typologyOptions])

  // Spec 0144 D-6: SAME already-rounded row amounts as `totals` above, bucketed
  // per product instead of aggregated — the two never disagree by construction.
  const productMargins = useMemo(
    () =>
      computeProductMargins(
        productLinesFromFormOfferLines(offerLines, costLines, productNameFor),
        costLinesFromFormCostLines(costLines),
      ),
    [offerLines, costLines, productNameFor],
  )

  return (
    <div className="flex flex-col gap-3">
      <QuoteSummary
        totals={totals}
        commissionTotals={commissionTotals}
        typologyBuckets={typologyBuckets}
      />
      <QuoteProductMargins
        rows={productMargins.rows}
        genericCostNet={productMargins.genericCostNet}
        showCommissions={fieldPermission('commissions').visible}
      />
    </div>
  )
}
