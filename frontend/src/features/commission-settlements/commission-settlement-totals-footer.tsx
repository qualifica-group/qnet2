import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { TableRowsAggregates } from '@/features/table/types'

const FOOTER_KEYS = [
  'taxable_amount',
  'vat_amount',
  'withholding_amount',
  'enasarco_agent_amount',
  'enasarco_principal_amount',
  'net_payable',
] as const

/** Totals of the whole filtered set (server `meta.aggregates`); hidden amounts are absent and skipped. */
export function CommissionSettlementTotalsFooter({ aggregates }: { aggregates: TableRowsAggregates | undefined }) {
  const { t } = useTranslation()
  const present = FOOTER_KEYS.filter((key) => aggregates?.[key] !== undefined)
  if (!aggregates || present.length === 0) {
    return null
  }
  return (
    <div role="group" aria-label={t('commissionSettlements.footer.label')}>
      <dl className="flex flex-wrap items-center justify-end gap-x-5 gap-y-1 text-xs">
        {present.map((key) => (
          <div key={key} className="flex items-baseline gap-1.5">
            <dt className="text-muted-foreground">{t(`commissionSettlements.columns.${key}`)}</dt>
            <dd className="font-semibold tabular-nums text-foreground">{formatEuro(aggregates[key])}</dd>
          </div>
        ))}
      </dl>
    </div>
  )
}
