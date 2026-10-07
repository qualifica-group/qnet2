import { useTranslation } from 'react-i18next'
import { money } from '@/features/work-order-contract-data/contract-data-format'
import type { ContractDataTotals as Totals } from '@/features/work-order-contract-data/types'

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-0.5 rounded-lg border bg-card px-3 py-2">
      <dt className="text-[11px] text-muted-foreground">{label}</dt>
      <dd className="text-sm font-semibold tabular-nums">{money(value)}</dd>
    </div>
  )
}

/**
 * Totals strip: net amount, then one card per typology the server sends (net
 * and effective revenue, none hard-wired here), then the total revenue. The
 * commission figures arrive `null` (and are not shown) when the actor cannot
 * see them.
 */
export function ContractDataTotals({ totals }: { totals: Totals }) {
  const { t } = useTranslation()

  return (
    <dl className="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
      <Stat label={t('workOrders.contractData.totals.netAmount')} value={totals.net_amount} />
      {totals.typologies.map((typology) => (
        <div key={typology.id} className="flex flex-col gap-0.5 rounded-lg border bg-card px-3 py-2">
          <dt className="truncate text-[11px] text-muted-foreground">{typology.name}</dt>
          <dd className="flex flex-col text-xs tabular-nums">
            <span>
              <span className="text-muted-foreground">{t('workOrders.contractData.totals.typologyNet')}</span>{' '}
              <span className="font-semibold">{money(typology.net_amount)}</span>
            </span>
            <span>
              <span className="text-muted-foreground">{t('workOrders.contractData.totals.typologyRevenue')}</span>{' '}
              <span className="font-semibold">{money(typology.effective_revenue)}</span>
            </span>
          </dd>
        </div>
      ))}
      <Stat label={t('workOrders.contractData.totals.revenue')} value={totals.effective_revenue} />
      {totals.commissions_amount !== null ? (
        <Stat label={t('workOrders.contractData.totals.commissions')} value={totals.commissions_amount} />
      ) : null}
      {totals.net_of_commissions !== null ? (
        <Stat label={t('workOrders.contractData.totals.netOfCommissions')} value={totals.net_of_commissions} />
      ) : null}
    </dl>
  )
}
