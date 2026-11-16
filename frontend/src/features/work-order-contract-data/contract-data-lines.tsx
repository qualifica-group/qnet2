import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { CreditCard } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { ContractDataLineFields } from '@/features/work-order-contract-data/contract-data-line-fields'
import { ContractPaymentBulkDialog } from '@/features/work-order-contract-data/contract-payment-bulk-dialog'
import { ContractPaymentDialog } from '@/features/work-order-contract-data/contract-payment-dialog'
import type { ContractDataLine } from '@/features/work-order-contract-data/types'

interface ContractDataLinesProps {
  workOrderId: number
  lines: ContractDataLine[]
  canManage: boolean
  /** Multi-offer work order: each line names its own client. */
  showRegistry: boolean
}

/**
 * The commessa's products one after the other, each as its own block of
 * fields (spec 0403, legacy layout). With `manage_payments` the pencil of a
 * line opens its payment dialog, and with more than one line "Set payment on
 * every line" opens the bulk dialog (spec 0333) on all of them.
 */
export function ContractDataLines({ workOrderId, lines, canManage, showRegistry }: ContractDataLinesProps) {
  const { t } = useTranslation()
  const [editingLineId, setEditingLineId] = useState<number | null>(null)
  const [isBulkOpen, setIsBulkOpen] = useState(false)
  const editingLine = lines.find((line) => line.quote_line_id === editingLineId) ?? null
  const closeBulk = () => setIsBulkOpen(false)

  return (
    <div className="flex flex-col gap-2">
      {canManage && lines.length > 1 ? (
        <div className="flex justify-end">
          <Button type="button" variant="outline" size="xs" className="gap-1.5 bg-card" onClick={() => setIsBulkOpen(true)}>
            <CreditCard className="size-3.5" aria-hidden="true" />
            {t('workOrders.contractData.bulk.setAll')}
          </Button>
        </div>
      ) : null}
      {lines.map((line) => (
        <ContractDataLineFields
          key={line.quote_line_id}
          line={line}
          canManage={canManage}
          showRegistry={showRegistry}
          onEdit={() => setEditingLineId(line.quote_line_id)}
        />
      ))}
      {editingLine ? (
        <ContractPaymentDialog workOrderId={workOrderId} line={editingLine} onClose={() => setEditingLineId(null)} />
      ) : null}
      {isBulkOpen ? (
        <ContractPaymentBulkDialog
          workOrderId={workOrderId}
          quoteLineIds={lines.map((line) => line.quote_line_id)}
          onClose={closeBulk}
          onSuccess={closeBulk}
        />
      ) : null}
    </div>
  )
}
