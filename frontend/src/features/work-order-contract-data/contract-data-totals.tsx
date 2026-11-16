import { useTranslation } from 'react-i18next'
import { RecordField, RecordFieldList } from '@/components/detail/record-panel'
import { CalculationHint } from '@/components/calculation-hint'
import { money } from '@/features/work-order-contract-data/contract-data-format'
import { totalsHint, type TotalsHintKind } from '@/features/work-order-contract-data/contract-data-formulas'
import type { ContractDataTotals as Totals, ContractTypologyTotal } from '@/features/work-order-contract-data/types'
import { cn } from '@/lib/utils'

interface ExplainedTotalProps {
  kind: TotalsHintKind
  totals: Totals
  value: string
  emphasis?: boolean
}

/** A total row whose figure explains its calculation on hover/focus/tap. */
function ExplainedTotal({ kind, totals, value, emphasis = false }: ExplainedTotalProps) {
  const { t } = useTranslation()
  const hint = totalsHint(kind, totals, t)

  return (
    <RecordField label={t(`workOrders.contractData.totals.${kind}`)}>
      <span className={cn('tabular-nums', emphasis ? 'font-semibold' : 'font-medium')}>
        <CalculationHint title={hint.title} lines={hint.lines}>
          {money(value)}
        </CalculationHint>
      </span>
    </RecordField>
  )
}

const isZero = (value: string) => Number(value) === 0

/** "Totale Consulenza", "Totale Ente": the typology's net amount, its revenue beside it when they differ. */
function TypologyTotal({ typology }: { typology: ContractTypologyTotal }) {
  const { t } = useTranslation()
  const isEmpty = isZero(typology.net_amount) && isZero(typology.effective_revenue)

  return (
    <RecordField label={t('workOrders.contractData.totals.typology', { name: typology.name })}>
      <span className={cn('font-medium tabular-nums', isEmpty && 'text-muted-foreground')}>
        {money(typology.net_amount)}
        {typology.effective_revenue !== typology.net_amount ? (
          <span className="ml-2 text-xs font-normal text-muted-foreground">
            {t('workOrders.contractData.totals.typologyRevenue', { revenue: money(typology.effective_revenue) })}
          </span>
        ) : null}
      </span>
    </RecordField>
  )
}

/**
 * The totals under the lines, as the legacy "Dati contrattuali commessa"
 * closes (user directive 2026-11-16): one row per typology ("Totale
 * Consulenza", "Totale Ente", server-ordered and zero-filled, the empty ones
 * dimmed), the commission totals when the server sends them, then "Totale
 * ricavi" last and emphasised.
 */
export function ContractDataTotals({ totals }: { totals: Totals }) {
  return (
    <RecordFieldList>
      {totals.typologies.map((typology) => (
        <TypologyTotal key={typology.id} typology={typology} />
      ))}
      {totals.commissions_amount !== null ? (
        <ExplainedTotal kind="commissions" totals={totals} value={totals.commissions_amount} />
      ) : null}
      {totals.net_of_commissions !== null ? (
        <ExplainedTotal kind="netOfCommissions" totals={totals} value={totals.net_of_commissions} />
      ) : null}
      <ExplainedTotal kind="revenue" totals={totals} value={totals.effective_revenue} emphasis />
    </RecordFieldList>
  )
}
