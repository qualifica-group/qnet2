import { useTranslation } from 'react-i18next'
import { TriangleAlert } from 'lucide-react'
import { CalculationHint } from '@/features/work-order-contract-data/calculation-hint'
import type { ContractDataWarning } from '@/features/work-order-contract-data/types'

const WARNING_KEYS: Record<ContractDataWarning, string> = {
  missing_supplier_commission: 'workOrders.contractData.warnings.missingSupplierCommission',
  stale_commission_base: 'workOrders.contractData.warnings.staleCommissionBase',
}

/**
 * Warnings of a line as warning icons: the text is in the hint (mouse, focus,
 * tap) and also `sr-only`, so the signal never rests on colour alone.
 */
export function ContractDataWarnings({ warnings }: { warnings: ContractDataWarning[] }) {
  const { t } = useTranslation()

  return warnings.map((warning) => {
    const text = t(WARNING_KEYS[warning])
    return (
      <CalculationHint key={warning} title={t('workOrders.contractData.warnings.title')} lines={[text]} className="shrink-0 text-destructive no-underline">
        <TriangleAlert aria-hidden="true" className="size-3.5" />
        <span className="sr-only">{text}</span>
      </CalculationHint>
    )
  })
}
