import { useTranslation } from 'react-i18next'
import { FileText } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordSection } from '@/components/detail/record-panel'
import { WorkOrderContractDataSection } from '@/features/work-order-contract-data/work-order-contract-data-section'
import type { WorkOrderDetailWithPermissions, WorkOrderQuoteLine } from '@/features/work-orders/types'

/**
 * Plain list of the linked offer's REVENUE lines (D-6/D-7), ordered like the
 * picker: what an actor without `view_contract_data` sees in place of the
 * contract data table (the work order payload carries no typology on its lines).
 */
function WorkOrderLinesList({ lines }: { lines: WorkOrderQuoteLine[] }) {
  if (lines.length === 0) {
    return <DetailEmpty />
  }

  const sorted = [...lines].sort((a, b) => a.sort_order - b.sort_order)

  return (
    <ul className="flex flex-col gap-1 text-sm">
      {sorted.map((line) => (
        <li key={line.id} className="flex min-w-0 items-baseline gap-2">
          {line.product ? (
            <>
              <span className="shrink-0 font-mono text-xs text-muted-foreground">{line.product.code}</span>
              <RecordLink domain="products" id={line.product.id} className="min-w-0 font-medium">
                {line.product.name}
              </RecordLink>
            </>
          ) : (
            <DetailEmpty />
          )}
        </li>
      ))}
    </ul>
  )
}

/**
 * "Contract data" section of the record card, full width. With
 * `view_contract_data` it is the contract data table (KPIs, revenue per
 * typology, lines with their calculation hints and payment actions); without
 * it, the plain list of the product lines. The lines themselves are not
 * editable on a work order: they are chosen when it is created.
 */
export function WorkOrderContractDataPanel({ workOrder, className }: { workOrder: WorkOrderDetailWithPermissions; className?: string }) {
  const { t } = useTranslation()
  const { actions } = workOrder.permissions

  return (
    <RecordSection title={t('workOrders.contractData.title')} icon={<FileText />} full className={className}>
      {actions.view_contract_data ? (
        <WorkOrderContractDataSection workOrderId={workOrder.id} canManage={actions.manage_payments === true} />
      ) : (
        <WorkOrderLinesList lines={workOrder.quote_lines} />
      )}
    </RecordSection>
  )
}
