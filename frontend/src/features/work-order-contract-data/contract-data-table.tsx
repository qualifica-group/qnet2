import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ContractDataRow } from '@/features/work-order-contract-data/contract-data-row'
import type { WorkOrderContractData } from '@/features/work-order-contract-data/types'

/** Columns that exist whatever the actor sees: product, quantity, unit price, net, revenue, payment. */
const BASE_COLUMN_COUNT = 6
/** The two commission columns (Supplier commission, net of commissions). */
const COMMISSION_COLUMN_COUNT = 2

const HEAD_CELL = 'px-2 py-1.5 font-medium'

interface ContractDataTableProps {
  workOrderId: number
  data: WorkOrderContractData
  canManage: boolean
}

/** The lines table. It scrolls inside its own container, never the page. One payment editor open at a time. */
export function ContractDataTable({ workOrderId, data, canManage }: ContractDataTableProps) {
  const { t } = useTranslation()
  const [editingLineId, setEditingLineId] = useState<number | null>(null)
  const columnCount = BASE_COLUMN_COUNT + (data.commissions_visible ? COMMISSION_COLUMN_COUNT : 0)

  return (
    <div className="overflow-x-auto rounded-lg border bg-surface">
      <table className="w-full min-w-[720px] text-xs">
        <caption className="sr-only">{t('workOrders.contractData.tableCaption')}</caption>
        <thead className="bg-muted/40 text-[11px] text-muted-foreground">
          <tr>
            <th scope="col" className={`${HEAD_CELL} text-left`}>{t('workOrders.contractData.columns.product')}</th>
            <th scope="col" className={`${HEAD_CELL} text-right`}>{t('workOrders.contractData.columns.quantity')}</th>
            <th scope="col" className={`${HEAD_CELL} text-right`}>{t('workOrders.contractData.columns.unitPrice')}</th>
            <th scope="col" className={`${HEAD_CELL} text-right`}>{t('workOrders.contractData.columns.netAmount')}</th>
            {data.commissions_visible ? (
              <>
                <th scope="col" className={`${HEAD_CELL} text-right`}>{t('workOrders.contractData.columns.supplierCommission')}</th>
                <th scope="col" className={`${HEAD_CELL} text-right`}>{t('workOrders.contractData.columns.netOfCommissions')}</th>
              </>
            ) : null}
            <th scope="col" className={`${HEAD_CELL} text-right`}>{t('workOrders.contractData.columns.effectiveRevenue')}</th>
            <th scope="col" className={`${HEAD_CELL} text-left`}>{t('workOrders.contractData.columns.payment')}</th>
          </tr>
        </thead>
        <tbody>
          {data.lines.map((line) => (
            <ContractDataRow
              key={line.quote_line_id}
              workOrderId={workOrderId}
              line={line}
              commissionsVisible={data.commissions_visible}
              columnCount={columnCount}
              canManage={canManage}
              isEditing={editingLineId === line.quote_line_id}
              onEdit={() => setEditingLineId(line.quote_line_id)}
              onCloseEditor={() => setEditingLineId(null)}
            />
          ))}
        </tbody>
      </table>
    </div>
  )
}
