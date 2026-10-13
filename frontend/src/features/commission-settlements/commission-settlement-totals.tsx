import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { SettlementDetail } from '@/features/commission-settlements/types'

interface TotalRowProps {
  label: string
  /** Amount to display; `undefined` = hidden by field permission, the row is omitted. */
  value: string | undefined
  strong?: boolean
  /** Deductions are shown with a minus sign. */
  negative?: boolean
  muted?: boolean
}

function signed(value: string, negative: boolean): string {
  const amount = Number(value)
  return formatEuro(negative && amount !== 0 ? -amount : amount)
}

function TotalRow({ label, value, strong = false, negative = false, muted = false }: TotalRowProps) {
  if (value === undefined) {
    return null
  }
  return (
    <div className={cn('flex items-baseline justify-between gap-3 py-1', strong && 'border-t border-border pt-2')}>
      <dt className={cn('text-xs', muted ? 'text-muted-foreground' : 'text-foreground', strong && 'font-semibold')}>{label}</dt>
      <dd className={cn('text-xs tabular-nums', strong ? 'text-sm font-semibold' : 'font-medium', muted && 'text-muted-foreground')}>
        {signed(value, negative)}
      </dd>
    </div>
  )
}

function percent(value: string | null): string | null {
  return value === null ? null : `${Number(value)}%`
}

/**
 * D-2 breakdown of a settlement: taxable, VAT, withholding, ENASARCO (agent
 * share deducted, principal share informative only) and net payable. An amount
 * the actor may not see is absent from the payload and its row is omitted.
 */
export function CommissionSettlementTotals({ settlement }: { settlement: SettlementDetail }) {
  const { t } = useTranslation()
  const vatRate = percent(settlement.vat_rate)
  const withholdingRate = percent(settlement.withholding_rate)
  const withholdingBase = percent(settlement.withholding_base_percent)

  return (
    <dl aria-label={t('commissionSettlements.totals.label')} className="flex flex-col">
      <TotalRow label={t('commissionSettlements.totals.accruals')} value={settlement.accruals_amount} />
      <TotalRow label={t('commissionSettlements.totals.adjustments')} value={settlement.adjustments_amount} />
      <TotalRow label={t('commissionSettlements.totals.taxable')} value={settlement.taxable_amount} strong />
      <TotalRow
        label={vatRate ? t('commissionSettlements.totals.vatRate', { rate: vatRate }) : t('commissionSettlements.totals.vatNone')}
        value={settlement.vat_amount}
      />
      <TotalRow
        label={
          settlement.withholding_enabled && withholdingRate && withholdingBase
            ? t('commissionSettlements.totals.withholdingRate', { rate: withholdingRate, base: withholdingBase })
            : t('commissionSettlements.totals.withholding')
        }
        value={settlement.withholding_amount}
        negative
      />
      <TotalRow
        label={t('commissionSettlements.totals.enasarcoAgent')}
        value={settlement.enasarco_agent_amount}
        negative
      />
      <TotalRow
        label={t('commissionSettlements.totals.enasarcoPrincipal')}
        value={settlement.enasarco_principal_amount}
        muted
      />
      <TotalRow label={t('commissionSettlements.totals.net')} value={settlement.net_payable} strong />
    </dl>
  )
}
