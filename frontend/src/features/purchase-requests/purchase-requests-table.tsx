import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Lock, Plus, Send } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { purchaseRequestColumnRenderers } from '@/features/purchase-requests/column-renderers'
import { LineStatusTabs } from '@/features/purchase-requests/line-status-tabs'
import { PurchaseRequestDetailPanel } from '@/features/purchase-requests/purchase-request-detail-panel'
import { PurchaseRequestCloseDialog } from '@/features/purchase-requests/purchase-request-close-dialog'
import { usePurchaseRequestDelete } from '@/features/purchase-requests/use-purchase-request-actions'
import { notifyPurchaseRequestManager } from '@/features/purchase-requests/api'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'
import { useStatusTabFilter } from '@/features/purchase-requests/use-status-tab-filter'
import { PURCHASE_REQUESTS_DOMAIN } from '@/features/purchase-requests/types'

/** Grid filter key of the status tabs: RDA with at least one line in that status (spec 0208 contract). */
const LINE_STATUS_FILTER_KEY = 'line_status'

/** Icon names the backend catalog uses beyond the shared defaults (`notify_manager`, `close`). */
const PURCHASE_ACTION_ICONS: ActionIconMap = { send: Send, lock: Lock }

/**
 * Thin RDA list adapter over the generic table: status tabs write into the
 * grid filter model; each row expands to its lines (master/detail); opening and editing navigate to the dedicated page;
 * close and delete run here (the generic table asks for the delete
 * confirmation). The backend re-authorizes every call.
 */
export function PurchaseRequestsTable() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const tabs = useStatusTabFilter(tableRef, LINE_STATUS_FILTER_KEY)
  const removeRequest = usePurchaseRequestDelete()

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [closeId, setCloseId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      if (await removeRequest(Number(row.id))) {
        refreshGrid()
      }
      setDeletingId(null)
    },
    [refreshGrid, removeRequest],
  )

  const notifyManager = useCallback(
    async (id: number) => {
      try {
        toast.success(await notifyPurchaseRequestManager(id))
      } catch (error) {
        toast.error(serverMessage(error) ?? t('purchaseRequests.messages.notifyError'))
      }
    },
    [t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
        case 'update':
          void navigate(`/${PURCHASE_REQUESTS_DOMAIN}/${row.id}`)
          break
        case 'notify_manager':
          void notifyManager(Number(row.id))
          break
        case 'close':
          setCloseId(Number(row.id))
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
    [navigate, notifyManager, runDelete],
  )

  const renderDetail = useCallback(
    (params: ICellRendererParams<TableRow>) => <PurchaseRequestDetailPanel {...params} onChanged={refreshGrid} />,
    [refreshGrid],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="purchase-requests.create">
            <Button onClick={() => void navigate(`/${PURCHASE_REQUESTS_DOMAIN}/new`)}>
              <Plus aria-hidden="true" />
              {t('purchaseRequests.newRequest')}
            </Button>
          </Can>
        }
      />
      <LineStatusTabs active={tabs.active} onSelect={tabs.select} label={t('purchaseRequests.tabs.label')} />

      <TableView
        ref={tableRef}
        domain={PURCHASE_REQUESTS_DOMAIN}
        renderers={purchaseRequestColumnRenderers}
        iconMap={PURCHASE_ACTION_ICONS}
        onAction={handleAction}
        onFilterModelChange={tabs.onFilterModelChange}
        isBusy={isBusy}
        masterDetail
        detailCellRenderer={renderDetail}
        detailRowAutoHeight
      />

      {closeId !== null ? (
        <PurchaseRequestCloseDialog
          requestId={closeId}
          open
          onOpenChange={(open) => (open ? undefined : setCloseId(null))}
          onClosed={() => {
            setCloseId(null)
            refreshGrid()
          }}
        />
      ) : null}

      <ResourceActivityDialog
        resource={PURCHASE_REQUESTS_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => (open ? undefined : setActivityRow(null))}
      />
    </div>
  )
}
