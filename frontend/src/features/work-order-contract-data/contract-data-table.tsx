import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Checkbox } from '@/components/ui/checkbox'
import { ContractDataBulkBar } from '@/features/work-order-contract-data/contract-data-bulk-bar'
import { ContractDataRow } from '@/features/work-order-contract-data/contract-data-row'
import { ContractPaymentDialog } from '@/features/work-order-contract-data/contract-payment-dialog'
import type { WorkOrderContractData } from '@/features/work-order-contract-data/types'

/** Opaque twin of the header's `bg-muted/40` wash, so the pinned cell hides what scrolls under it. */
const STICKY_ACTIONS_HEAD =
  'sticky right-0 z-10 bg-[color-mix(in_srgb,var(--muted)_40%,var(--surface))] shadow-[-6px_0_6px_-6px_rgb(0_0_0/0.25)]'

const HEAD_CELL = 'px-2 py-1.5 font-medium whitespace-nowrap'

interface HeadProps {
  /** Key of `columns`; the full label is the accessible name and the tooltip. */
  column: string
  /** Key of `columnsShort`, when the column is abbreviated. */
  short?: string
  align?: 'left' | 'right'
}

function Head({ column, short, align = 'right' }: HeadProps) {
  const { t } = useTranslation()
  const full = t(`workOrders.contractData.columns.${column}`)

  return (
    <th scope="col" aria-label={full} className={`${HEAD_CELL} ${align === 'right' ? 'text-right' : 'text-left'}`}>
      <span title={full}>{short ? t(`workOrders.contractData.columnsShort.${short}`) : full}</span>
    </th>
  )
}

/** "Select all" state of the header checkbox. */
function selectAllState(selectedCount: number, lineCount: number): boolean | 'indeterminate' {
  if (selectedCount === 0) return false
  return selectedCount === lineCount ? true : 'indeterminate'
}

interface ContractDataTableProps {
  workOrderId: number
  data: WorkOrderContractData
  canManage: boolean
  /** Multi-offer work order: each line names its own client. */
  showRegistry: boolean
}

/**
 * The lines. From `md` a one-row-per-line table that scrolls inside its own
 * container when narrow; below `md` the same markup lays each line out as a
 * compact card (one DOM, so tooltips and the actions button stay single).
 */
export function ContractDataTable({ workOrderId, data, canManage, showRegistry }: ContractDataTableProps) {
  const { t } = useTranslation()
  const [editingLineId, setEditingLineId] = useState<number | null>(null)
  const editingLine = data.lines.find((line) => line.quote_line_id === editingLineId) ?? null
  const [selectedIds, setSelectedIds] = useState<ReadonlySet<number>>(() => new Set())
  // Only ids still among the lines count: a line removed by a refetch drops out of the selection.
  const selectedLineIds = data.lines.map((line) => line.quote_line_id).filter((id) => selectedIds.has(id))

  const toggleLine = (id: number) =>
    setSelectedIds((current) => {
      const next = new Set(current)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  const toggleAll = () =>
    setSelectedIds(selectedLineIds.length === data.lines.length ? new Set() : new Set(data.lines.map((line) => line.quote_line_id)))
  const clearSelection = () => setSelectedIds(new Set())

  return (
    <div className="relative overflow-x-auto rounded-lg border bg-surface">
      {canManage ? (
        <div className="flex items-center gap-2 border-b px-2 py-1.5 md:hidden">
          <Checkbox
            checked={selectAllState(selectedLineIds.length, data.lines.length)}
            onCheckedChange={toggleAll}
            aria-label={t('workOrders.contractData.bulk.selectAll')}
          />
          <span className="text-xs text-muted-foreground">{t('workOrders.contractData.bulk.selectAll')}</span>
        </div>
      ) : null}
      {selectedLineIds.length > 0 ? (
        <ContractDataBulkBar workOrderId={workOrderId} selectedLineIds={selectedLineIds} onClear={clearSelection} />
      ) : null}
      <table className="block w-full text-xs md:table md:min-w-[1100px]">
        <caption className="sr-only">{t('workOrders.contractData.tableCaption')}</caption>
        <thead className="hidden bg-muted/40 text-[11px] text-muted-foreground md:table-header-group">
          <tr>
            {canManage ? (
              <th scope="col" className={`${HEAD_CELL} w-px`}>
                <Checkbox
                  checked={selectAllState(selectedLineIds.length, data.lines.length)}
                  onCheckedChange={toggleAll}
                  aria-label={t('workOrders.contractData.bulk.selectAll')}
                  className="align-middle"
                />
              </th>
            ) : null}
            <Head column="product" align="left" />
            <Head column="quantity" />
            <Head column="unitPrice" short="unitPrice" />
            <Head column="netAmount" />
            {data.commissions_visible ? (
              <>
                <Head column="supplierCommission" short="supplierCommission" />
                <Head column="netOfCommissions" short="netOfCommissions" />
              </>
            ) : null}
            <Head column="effectiveRevenue" short="effectiveRevenue" />
            <Head column="payment" align="left" />
            {canManage ? (
              <th scope="col" className={`${HEAD_CELL} ${STICKY_ACTIONS_HEAD}`}>
                <span className="sr-only">{t('workOrders.contractData.columns.actions')}</span>
              </th>
            ) : null}
          </tr>
        </thead>
        <tbody className="block md:table-row-group">
          {data.lines.map((line) => (
            <ContractDataRow
              key={line.quote_line_id}
              line={line}
              commissionsVisible={data.commissions_visible}
              canManage={canManage}
              showRegistry={showRegistry}
              isSelected={selectedIds.has(line.quote_line_id)}
              onToggleSelection={() => toggleLine(line.quote_line_id)}
              onEdit={() => setEditingLineId(line.quote_line_id)}
            />
          ))}
        </tbody>
      </table>
      {editingLine ? (
        <ContractPaymentDialog workOrderId={workOrderId} line={editingLine} onClose={() => setEditingLineId(null)} />
      ) : null}
    </div>
  )
}
