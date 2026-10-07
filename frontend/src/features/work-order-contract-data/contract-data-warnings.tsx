import { useTranslation } from 'react-i18next'
import { TriangleAlert } from 'lucide-react'
import type { ContractDataWarning } from '@/features/work-order-contract-data/types'

const WARNING_KEYS: Record<ContractDataWarning, string> = {
  missing_supplier_commission: 'workOrders.contractData.warnings.missingSupplierCommission',
  stale_commission_base: 'workOrders.contractData.warnings.staleCommissionBase',
}

/** Warnings of a line: icon plus text, so the signal never rests on colour alone. */
export function ContractDataWarnings({ warnings }: { warnings: ContractDataWarning[] }) {
  const { t } = useTranslation()

  if (warnings.length === 0) {
    return null
  }

  return (
    <ul className="flex flex-col gap-0.5">
      {warnings.map((warning) => (
        <li key={warning} className="flex items-center gap-1.5 text-[11px] font-medium text-destructive">
          <TriangleAlert aria-hidden="true" className="size-3.5 shrink-0" />
          {t(WARNING_KEYS[warning])}
        </li>
      ))}
    </ul>
  )
}
