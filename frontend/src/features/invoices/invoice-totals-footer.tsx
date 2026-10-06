import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { TableRowsAggregates } from '@/features/table/types'

const FOOTER_KEYS = ['net_amount', 'vat_amount', 'total_amount', 'collected_amount', 'residual_amount'] as const

/** Totals of the whole filtered set (server `meta.aggregates`), not just the loaded page. */
export function InvoiceTotalsFooter({ aggregates }: { aggregates: TableRowsAggregates | undefined }) {
  const { t } = useTranslation()
  if (!aggregates) {
    return null
  }
  return (
    <div role="group" aria-label={t('invoices.footer.label')}>
      <dl className="flex flex-wrap items-center justify-end gap-x-5 gap-y-1 text-xs">
        {FOOTER_KEYS.map((key) => (
          <div key={key} className="flex items-baseline gap-1.5">
            <dt className="text-muted-foreground">{t(`invoices.footer.${key}`)}</dt>
            <dd className="font-semibold tabular-nums text-foreground">{formatEuro(aggregates[key] ?? 0)}</dd>
          </div>
        ))}
      </dl>
    </div>
  )
}
