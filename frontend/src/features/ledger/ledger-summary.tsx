import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { LedgerAging, LedgerSide, LedgerSummary } from '@/features/ledger/types'

const OVERDUE_BUCKETS: readonly (keyof LedgerAging)[] = [
  'overdue_1_30',
  'overdue_31_60',
  'overdue_61_90',
  'overdue_over_90',
]
const NOT_DUE_BUCKETS: readonly (keyof LedgerAging)[] = ['due_0_30', 'due_31_60', 'due_over_60']

function Figure({ label, value, emphasis = false }: { label: string; value: number; emphasis?: boolean }) {
  return (
    <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
      <dt className="truncate text-xs text-muted-foreground">{label}</dt>
      <dd className={emphasis ? 'text-base font-semibold tabular-nums' : 'text-sm font-medium tabular-nums'}>
        {formatEuro(value)}
      </dd>
    </div>
  )
}

function AgingGroup({ title, buckets, aging }: { title: string; buckets: readonly (keyof LedgerAging)[]; aging: LedgerAging }) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col gap-1.5">
      <h4 className="text-xs font-medium text-muted-foreground">{title}</h4>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {buckets.map((bucket) => (
          <Figure key={bucket} label={t(`ledger.summary.${bucket}`)} value={aging[bucket]} />
        ))}
      </dl>
    </div>
  )
}

/** Totals of one ledger section plus the aging of its open installments (spec 0266 D-9). */
export function LedgerSummaryPanel({ summary, side }: { summary: LedgerSummary; side: LedgerSide }) {
  const { t } = useTranslation()
  const settledLabel = side === 'customer' ? 'ledger.summary.settledCustomer' : 'ledger.summary.settledSupplier'

  return (
    <div className="flex flex-col gap-3">
      <dl className="grid grid-cols-2 gap-2 lg:grid-cols-5">
        <Figure label={t('ledger.summary.invoiced')} value={summary.invoiced_total} />
        <Figure label={t(settledLabel)} value={summary.settled_total} />
        <Figure label={t('ledger.summary.residual')} value={summary.residual_total} emphasis />
        <Figure label={t('ledger.summary.overdue')} value={summary.overdue_total} />
        <Figure label={t('ledger.summary.notDue')} value={summary.not_due_total} />
      </dl>
      <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
        <AgingGroup title={t('ledger.summary.agingOverdue')} buckets={OVERDUE_BUCKETS} aging={summary.aging} />
        <AgingGroup title={t('ledger.summary.agingNotDue')} buckets={NOT_DUE_BUCKETS} aging={summary.aging} />
      </div>
    </div>
  )
}
