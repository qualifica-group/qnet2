import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { useNavigate } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { purchaseOrderColumnRenderers } from '@/features/purchase-orders/column-renderers'
import { PurchaseOrderDetailPanel } from '@/features/purchase-orders/purchase-order-detail-panel'
import { StatusTabs } from '@/features/purchase-orders/status-tabs'
import { usePurchaseOrderDelete } from '@/features/purchase-orders/use-purchase-order-actions'
import { useStatusTabFilter } from '@/features/purchase-orders/use-status-tab-filter'
import { PURCHASE_ORDERS_DOMAIN } from '@/features/purchase-orders/types'

/** Grid filter key of the status tabs (spec 0210 contract: tab per stato). */
const STATUS_FILTER_KEY = 'status'

const renderDetail = (params: ICellRendererParams<TableRow>) => <PurchaseOrderDetailPanel {...params} />

/**
 * Thin ODA list adapter over the generic table: status tabs write into the
 * grid filter model; each row expands to its lines (master/detail); opening
 * and editing navigate to the dedicated page; delete runs here (the generic
 * table asks for the confirmation, and only offers it where the row's
 * `abilities.delete` allows). The backend re-authorizes every call.
 */
export function PurchaseOrdersTable() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const tabs = useStatusTabFilter(tableRef, STATUS_FILTER_KEY)
  const removeOrder = usePurchaseOrderDelete()

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      if (await removeOrder(Number(row.id))) {
        refreshGrid()
      }
      setDeletingId(null)
    },
    [refreshGrid, removeOrder],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
        case 'update':
          void navigate(`/${PURCHASE_ORDERS_DOMAIN}/${row.id}`)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        default:
          break
      }
    },
    [navigate, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="purchase-orders.create">
            <Button onClick={() => void navigate(`/${PURCHASE_ORDERS_DOMAIN}/new`)}>
              <Plus aria-hidden="true" />
              {t('purchaseOrders.newOrder')}
            </Button>
          </Can>
        }
      />
      <StatusTabs active={tabs.active} onSelect={tabs.select} label={t('purchaseOrders.tabs.label')} />

      <TableView
        ref={tableRef}
        domain={PURCHASE_ORDERS_DOMAIN}
        renderers={purchaseOrderColumnRenderers}
        onAction={handleAction}
        onFilterModelChange={tabs.onFilterModelChange}
        isBusy={isBusy}
        masterDetail
        detailCellRenderer={renderDetail}
        detailRowAutoHeight
      />

      <ResourceActivityDialog
        resource={PURCHASE_ORDERS_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => (open ? undefined : setActivityRow(null))}
      />
    </div>
  )
}
