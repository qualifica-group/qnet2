import { useTranslation } from 'react-i18next'
import { TrendingDown, TrendingUp, Wallet } from 'lucide-react'
import { cn } from '@/lib/utils'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { ContractAmountBreakdown, ContractSummary } from '@/features/contracts/types'

/** One net/vat/gross card (mirrors `QuoteAmountBlock`, without the commissions block the contract summary has no data for). */
function AmountBlock({
  icon: Icon,
  title,
  aggregate,
  netLabel,
  vatLabel,
  grossLabel,
}: {
  icon: typeof TrendingUp
  title: string
  aggregate: ContractAmountBreakdown
  netLabel: string
  vatLabel: string
  grossLabel: string
}) {
  return (
    <div className="flex flex-col gap-1.5 rounded-md border bg-card p-3">
      <div className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
        <Icon aria-hidden="true" className="size-3.5" />
        {title}
      </div>
      <dl className="flex flex-col gap-1 text-xs">
        <div className="flex items-center justify-between">
          <dt className="text-muted-foreground">{netLabel}</dt>
          <dd className="font-medium tabular-nums">{formatQuoteAmount(Number(aggregate.net))}</dd>
        </div>
        <div className="flex items-center justify-between">
          <dt className="text-muted-foreground">{vatLabel}</dt>
          <dd className="font-medium tabular-nums">{formatQuoteAmount(Number(aggregate.vat))}</dd>
        </div>
        <div className="flex items-center justify-between border-t pt-1">
          <dt className="text-muted-foreground">{grossLabel}</dt>
          <dd className="font-semibold tabular-nums">{formatQuoteAmount(Number(aggregate.gross))}</dd>
        </div>
      </dl>
    </div>
  )
}

/**
 * Read-only economic recap (BR-7: projected from the quote, never persisted
 * on `contracts`): revenue / cost / margin, the same three-block look as
 * `QuoteSummary` minus its commissions block — `ContractSummary` carries no
 * commissions data (out of the frozen `data_contract`).
 */
export function ContractSummaryPanel({ summary }: { summary: ContractSummary }) {
  const { t } = useTranslation()
  const marginNegative = Number(summary.margin.net) < 0

  return (
    <div className="rounded-lg border bg-surface p-3">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <AmountBlock
          icon={TrendingUp}
          title={t('quotes.form.summary.revenue')}
          aggregate={summary.revenue}
          netLabel={t('quotes.form.summary.net')}
          vatLabel={t('quotes.form.summary.vat')}
          grossLabel={t('quotes.form.summary.gross')}
        />
        <AmountBlock
          icon={TrendingDown}
          title={t('quotes.form.summary.cost')}
          aggregate={summary.cost}
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
            {formatQuoteAmount(Number(summary.margin.net))}
          </p>
        </div>
      </div>
    </div>
  )
}
