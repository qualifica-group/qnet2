import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { TableRowsAggregates } from '@/features/table/types'

const TOTAL_KEY = 'amount'

/** Total of the whole filtered set (server `meta.aggregates`), absent when the actor cannot see amounts. */
export function CommissionAccrualTotalsFooter({ aggregates }: { aggregates: TableRowsAggregates | undefined }) {
  const { t } = useTranslation()
  if (!aggregates || aggregates[TOTAL_KEY] === undefined) {
    return null
  }
  return (
    <div role="group" aria-label={t('commissionAccruals.footer.label')}>
      <dl className="flex flex-wrap items-center justify-end gap-x-5 gap-y-1 text-xs">
        <div className="flex items-baseline gap-1.5">
          <dt className="text-muted-foreground">{t('commissionAccruals.footer.amount')}</dt>
          <dd className="font-semibold tabular-nums text-foreground">{formatEuro(aggregates[TOTAL_KEY])}</dd>
        </div>
      </dl>
    </div>
  )
}
