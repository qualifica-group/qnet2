import { useTranslation } from 'react-i18next'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import { CalculationHint } from '@/features/work-order-contract-data/calculation-hint'
import { money } from '@/features/work-order-contract-data/contract-data-format'
import { totalsHint, type TotalsHintKind } from '@/features/work-order-contract-data/contract-data-formulas'
import type { ContractDataTotals as Totals } from '@/features/work-order-contract-data/types'
import { cn } from '@/lib/utils'

interface KpiProps {
  kind: TotalsHintKind
  totals: Totals
  value: string
  emphasis?: boolean
}

function Kpi({ kind, totals, value, emphasis = false }: KpiProps) {
  const { t } = useTranslation()
  const hint = totalsHint(kind, totals, t)

  return (
    <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card px-3 py-1.5">
      <dt className="truncate text-[11px] text-muted-foreground">{t(`workOrders.contractData.totals.${kind}`)}</dt>
      <dd className={cn('text-sm tabular-nums', emphasis ? 'font-semibold' : 'font-medium')}>
        <CalculationHint title={hint.title} lines={hint.lines}>
          {money(value)}
        </CalculationHint>
      </dd>
    </div>
  )
}

const isZero = (value: string) => Number(value) === 0

/**
 * Compact summary: one row of KPIs (the commission ones only when the server
 * sends them), then the revenue per typology as a strip. The typologies come
 * from the server already ordered and zero-filled; the empty ones are dimmed.
 */
export function ContractDataTotals({ totals }: { totals: Totals }) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2">
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <Kpi kind="netAmount" totals={totals} value={totals.net_amount} />
        <Kpi kind="revenue" totals={totals} value={totals.effective_revenue} emphasis />
        {totals.commissions_amount !== null ? (
          <Kpi kind="commissions" totals={totals} value={totals.commissions_amount} />
        ) : null}
        {totals.net_of_commissions !== null ? (
          <Kpi kind="netOfCommissions" totals={totals} value={totals.net_of_commissions} />
        ) : null}
      </dl>
      <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-xs">
        <span className="text-[11px] text-muted-foreground">{t('workOrders.contractData.totals.byTypology')}</span>
        <ul className="contents">
          {totals.typologies.map((typology) => (
            <li
              key={typology.id}
              className={cn('flex min-w-0 items-baseline gap-1.5 tabular-nums', isZero(typology.effective_revenue) && isZero(typology.net_amount) ? 'text-muted-foreground' : null)}
            >
              <ProductTypologyBadge name={typology.name} color={typology.color} />
              <span>
                {money(typology.net_amount)} → {money(typology.effective_revenue)}
              </span>
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}
