import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { Statement } from '@/features/account-statements/types'

function Figure({ label, value, emphasis = false }: { label: string; value: string; emphasis?: boolean }) {
  return (
    <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
      <dt className="truncate text-xs text-muted-foreground">{label}</dt>
      <dd className={emphasis ? 'text-base font-semibold tabular-nums' : 'text-sm font-medium tabular-nums'}>
        {formatEuro(value)}
      </dd>
    </div>
  )
}

/** Opening balances, period totals, closing balance and balance as of today. */
export function StatementSummary({ statement }: { statement: Statement }) {
  const { t } = useTranslation()
  return (
    <dl className="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8">
      <Figure label={t('accountStatements.summary.accountOpening')} value={statement.opening.balance} />
      <Figure label={t('accountStatements.summary.periodOpening')} value={statement.period_opening_balance} />
      <Figure label={t('accountStatements.summary.inflow')} value={statement.totals.inflow} />
      <Figure label={t('accountStatements.summary.outflow')} value={statement.totals.outflow} />
      <Figure label={t('accountStatements.summary.closing')} value={statement.closing_balance} emphasis />
      <Figure label={t('accountStatements.summary.today')} value={statement.balance_today} emphasis />
      <Figure label={t('accountStatements.summary.reconciled')} value={statement.reconciled_balance} />
      <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
        <dt className="truncate text-xs text-muted-foreground">{t('accountStatements.summary.unreconciledCount')}</dt>
        <dd className="text-sm font-medium tabular-nums">{statement.unreconciled_count}</dd>
      </div>
    </dl>
  )
}
