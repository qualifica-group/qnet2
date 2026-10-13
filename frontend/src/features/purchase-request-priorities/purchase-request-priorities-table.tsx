import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { ApiErrorResponse } from '@/api/types'
import { purchaseRequestPriorityColumnRenderers } from '@/features/purchase-request-priorities/column-renderers'
import { deletePurchaseRequestPriority } from '@/features/purchase-request-priorities/api'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'

/** Domain key used to mount the generic table for RDA priorities. */
const PURCHASE_REQUEST_PRIORITIES_DOMAIN = 'purchase-request-priorities'

/**
 * Thin PurchaseRequestPriorities adapter over the generic table. It mounts `<TableView>` with the
 * `purchase-request-priorities` domain, its custom cell renderers and a row-action handler, and
 * delegates the open mode (modal Sheet vs dedicated page) of view/edit/create
 * to `useModuleOpener`, resolved from the user's preference (spec 0042). It
 * still owns the delete flow, surfacing the backend's own message on the 409
 * "used by an RDA" guard (spec 0247 D-3), and refreshes the SSRM grid after
 * every mutation. The reorder toggle reuses the shared `StatusReorderToggle`
 * (spec 0247 D-3): this lookup has no system
 * rows, so every row is draggable and `ordered_ids` carries the whole table —
 * which is exactly what the shared sheet already sends when no option reports
 * a `meta.system_key`. Permission gating is an affordance only; the backend
 * re-authorizes each call.
 */
export function PurchaseRequestPrioritiesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(PURCHASE_REQUEST_PRIORITIES_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deletePurchaseRequestPriority(Number(row.id))
        toast.success(t('purchaseRequestPriorities.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('purchaseRequestPriorities.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('purchaseRequestPriorities.form.deleteForbidden'))
        } else if (status === 409) {
          // The row is still referenced by an RDA. Surface the
          // backend's own message rather than a generic one.
          toast.error(error.response?.data?.message ?? t('purchaseRequestPriorities.form.deleteInUse'))
        } else {
          toast.error(t('purchaseRequestPriorities.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
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
    [openView, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatusReorderToggle
              resource={PURCHASE_REQUEST_PRIORITIES_DOMAIN}
              permission="purchase-request-priorities.update"
              labels={{
                openButton: t('purchaseRequestPriorities.reorder.openButton'),
                title: t('purchaseRequestPriorities.reorder.title'),
                subtitle: t('purchaseRequestPriorities.reorder.subtitle'),
                dragHandleLabel: t('purchaseRequestPriorities.reorder.dragHandleLabel'),
                inactiveBadge: t('purchaseRequestPriorities.reorder.inactiveBadge'),
                loadError: t('purchaseRequestPriorities.reorder.loadError'),
                saved: t('purchaseRequestPriorities.reorder.saved'),
                forbidden: t('purchaseRequestPriorities.reorder.forbidden'),
                genericError: t('purchaseRequestPriorities.reorder.genericError'),
              }}
              onReordered={refreshGrid}
            />
            <Can permission="purchase-request-priorities.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('purchaseRequestPriorities.form.newPurchaseRequestPriority')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={PURCHASE_REQUEST_PRIORITIES_DOMAIN}
        renderers={purchaseRequestPriorityColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={PURCHASE_REQUEST_PRIORITIES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />
    </div>
  )
}
