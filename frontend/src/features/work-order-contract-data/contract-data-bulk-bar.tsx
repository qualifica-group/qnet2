import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { CreditCard, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { ContractPaymentBulkDialog } from '@/features/work-order-contract-data/contract-payment-bulk-dialog'

interface ContractDataBulkBarProps {
  workOrderId: number
  selectedLineIds: number[]
  onClear: () => void
}

/**
 * Toolbar of the selected lines (spec 0333, D-6): count, "Set payment" (bulk
 * dialog) and "Clear selection". Rendered by the table only with a selection.
 */
export function ContractDataBulkBar({ workOrderId, selectedLineIds, onClear }: ContractDataBulkBarProps) {
  const { t } = useTranslation()
  const [isDialogOpen, setIsDialogOpen] = useState(false)

  return (
    <div role="toolbar" aria-label={t('workOrders.contractData.bulk.setPayment')} className="flex flex-wrap items-center gap-1.5 border-b bg-card px-2 py-1.5">
      <span className="px-1 text-xs font-medium tabular-nums whitespace-nowrap">
        {t('workOrders.contractData.bulk.selectedCount', { count: selectedLineIds.length })}
      </span>
      <div className="h-4 w-px shrink-0 bg-border" aria-hidden="true" />
      <Button type="button" variant="outline" size="xs" className="gap-1.5 bg-card" onClick={() => setIsDialogOpen(true)}>
        <CreditCard className="size-3.5" aria-hidden="true" />
        {t('workOrders.contractData.bulk.setPayment')}
      </Button>
      <Button type="button" variant="ghost" size="xs" className="gap-1.5" onClick={onClear}>
        <X className="size-3.5" aria-hidden="true" />
        {t('workOrders.contractData.bulk.clear')}
      </Button>
      {isDialogOpen ? (
        <ContractPaymentBulkDialog
          workOrderId={workOrderId}
          quoteLineIds={selectedLineIds}
          onClose={() => setIsDialogOpen(false)}
          onSuccess={() => {
            setIsDialogOpen(false)
            onClear()
          }}
        />
      ) : null}
    </div>
  )
}
