import { useTranslation } from 'react-i18next'
import { HandCoins } from 'lucide-react'
import { cn } from '@/lib/utils'
import { round2 } from '@/features/quotes/quote-totals'
import { formatSignedQuoteAmount } from '@/features/quotes/quote-summary'
import type { CommissionTotals } from './commission-calculator'

const PAID_ROLES = ['commercial', 'reporter', 'supervisor'] as const

type Flow = 'in' | 'out'

interface CommissionSummaryEntry {
  key: string
  label: string
  amount: number
  flow: Flow
}

/**
 * Commercial, Reporter and Supervisor are always paid out; the Supplier goes
 * either way by its direction, so a quote with both directions shows the two
 * parts on their own rows instead of netting them.
 */
function useCommissionEntries(totals: CommissionTotals, receivedSupplier: number): CommissionSummaryEntry[] {
  const { t } = useTranslation()
  const paidSupplier = round2(totals.supplier - receivedSupplier)
  const supplierLabel = t('quotes.form.summary.roles.supplier')
  const entries: CommissionSummaryEntry[] = PAID_ROLES.map((role) => ({
    key: role,
    label: t(`quotes.form.summary.roles.${role}`),
    amount: totals[role],
    flow: 'out',
  }))

  if (receivedSupplier > 0 && paidSupplier > 0) {
    entries.push(
      { key: 'supplier-in', label: t('quotes.form.summary.supplierReceived'), amount: receivedSupplier, flow: 'in' },
      { key: 'supplier-out', label: t('quotes.form.summary.supplierPaid'), amount: paidSupplier, flow: 'out' },
    )
  } else if (receivedSupplier > 0) {
    entries.push({ key: 'supplier', label: supplierLabel, amount: receivedSupplier, flow: 'in' })
  } else {
    entries.push({ key: 'supplier', label: supplierLabel, amount: paidSupplier, flow: 'out' })
  }

  return entries
}

interface QuoteCommissionSummaryProps {
  totals: CommissionTotals
  /** The Supplier commissions received (included in `totals.supplier`): the rest is paid. */
  receivedSupplier: number
}

/**
 * The "Riepilogo Commissioni" card: each role's total with a + when it comes
 * in and a - when it goes out (user request 2026-10-23). A zero carries no sign.
 */
export function QuoteCommissionSummary({ totals, receivedSupplier }: QuoteCommissionSummaryProps) {
  const { t } = useTranslation()
  const entries = useCommissionEntries(totals, receivedSupplier)

  return (
    <div className="flex flex-col gap-1.5 rounded-md border bg-card p-3">
      <div className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
        <HandCoins aria-hidden="true" className="size-3.5" />
        {t('quotes.form.summary.commissions')}
      </div>
      <dl className="grid gap-1 text-xs">
        {entries.map((entry) => {
          const signed = entry.flow === 'in' ? entry.amount : -entry.amount
          return (
            <div key={entry.key} className="flex items-center justify-between gap-2">
              <dt className="min-w-0 truncate text-muted-foreground">{entry.label}</dt>
              <dd className={cn('shrink-0 font-medium tabular-nums', signed > 0 ? 'text-success' : undefined)}>
                {formatSignedQuoteAmount(signed)}
              </dd>
            </div>
          )
        })}
      </dl>
      <p className="text-[11px] text-muted-foreground">{t('quotes.form.summary.commissionsHint')}</p>
    </div>
  )
}
