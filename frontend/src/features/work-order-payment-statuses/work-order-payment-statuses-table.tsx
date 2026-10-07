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
import { workOrderPaymentStatusColumnRenderers } from '@/features/work-order-payment-statuses/column-renderers'
import { deleteWorkOrderPaymentStatus } from '@/features/work-order-payment-statuses/api'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'

/** Domain key used to mount the generic table for work order payment statuses. */
const WORK_ORDER_PAYMENT_STATUSES_DOMAIN = 'work-order-payment-statuses'

/**
 * Thin Work Order Payment Statuses adapter over the generic table. It mounts
 * `<TableView>` with the `work-order-payment-statuses` domain, its custom cell renderers
 * and a row-action handler, and delegates the open mode (modal Sheet vs
 * dedicated page) of view/edit/create to `useModuleOpener`, resolved from
 * the user's preference (spec 0042). It still owns the delete flow
 * (confirming + running the delete mutation, surfacing the backend's exact
 * 409 message when the status is still referenced by a work order line, BR-4) and
 * refreshing the SSRM grid after every mutation via the table's imperative
 * handle, and reuses the shared `status-reorder` feature
 * (`resource="work-order-payment-statuses"`) for the drag & drop sheet (no
 * system rows here). Permission gating is an
 * affordance only; the backend re-authorizes each call.
 */
export function WorkOrderPaymentStatusesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(WORK_ORDER_PAYMENT_STATUSES_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteWorkOrderPaymentStatus(Number(row.id))
        toast.success(t('workOrderPaymentStatuses.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('workOrderPaymentStatuses.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('workOrderPaymentStatuses.form.deleteForbidden'))
        } else if (status === 409) {
          // BR-4: the status is still referenced by a work order line. Surface the
          // backend's own message rather than a generic one.
          toast.error(
            error.response?.data?.message ?? t('workOrderPaymentStatuses.form.deleteInUseFallback'),
          )
        } else {
          toast.error(t('workOrderPaymentStatuses.form.deleteError'))
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
              resource={WORK_ORDER_PAYMENT_STATUSES_DOMAIN}
              permission="work-order-payment-statuses.update"
              labels={{
                openButton: t('workOrderPaymentStatuses.reorder.openButton'),
                title: t('workOrderPaymentStatuses.reorder.title'),
                subtitle: t('workOrderPaymentStatuses.reorder.subtitle'),
                dragHandleLabel: t('workOrderPaymentStatuses.reorder.dragHandleLabel'),
                loadError: t('workOrderPaymentStatuses.reorder.loadError'),
                saved: t('workOrderPaymentStatuses.reorder.saved'),
                forbidden: t('workOrderPaymentStatuses.reorder.forbidden'),
                genericError: t('workOrderPaymentStatuses.reorder.genericError'),
              }}
              onReordered={refreshGrid}
            />
            <Can permission="work-order-payment-statuses.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('workOrderPaymentStatuses.form.newWorkOrderPaymentStatus')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={WORK_ORDER_PAYMENT_STATUSES_DOMAIN}
        renderers={workOrderPaymentStatusColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={WORK_ORDER_PAYMENT_STATUSES_DOMAIN}
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
