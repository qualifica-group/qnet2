import { useTranslation } from 'react-i18next'
import { RecordField } from '@/components/detail/record-panel'
import { money } from '@/features/work-order-contract-data/contract-data-format'

/** "Importo" (spec 0402): the net amount of the commessa's lines; absent for an actor who may not see it. */
export function WorkOrderAmountRow({ amount }: { amount: string | null | undefined }) {
  const { t } = useTranslation()

  if (amount === null || amount === undefined) {
    return null
  }

  return (
    <RecordField label={t('workOrders.detail.amount')}>
      <span className="font-medium tabular-nums">{money(amount)}</span>
    </RecordField>
  )
}
