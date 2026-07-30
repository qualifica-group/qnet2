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
import { paymentMethodColumnRenderers } from '@/features/payment-methods/column-renderers'
import { deletePaymentMethod } from '@/features/payment-methods/api'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'

/** Domain key used to mount the generic table for payment methods. */
const PAYMENT_METHODS_DOMAIN = 'payment-methods'

/**
 * Thin Payment Methods adapter over the generic table. It mounts
 * `<TableView>` with the `payment-methods` domain, its custom cell renderers
 * and a row-action handler, and delegates the open mode (modal Sheet vs
 * dedicated page) of view/edit/create to `useModuleOpener`, resolved from
 * the user's preference (spec 0042). It still owns the delete flow
 * (confirming + running the delete mutation) and refreshing the SSRM grid
 * after every mutation via the table's imperative handle — no 409 branch is
 * needed here (D-2: no delete guard in this iteration, the endpoint always
 * responds 204) — and reuses the shared `status-reorder` feature
 * (`resource="payment-methods"`) for the drag & drop sheet (D-1). Permission
 * gating is an affordance only; the backend re-authorizes each call.
 */
export function PaymentMethodsTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(PAYMENT_METHODS_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deletePaymentMethod(row.id)
        toast.success(t('paymentMethods.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('paymentMethods.form.deleteForbidden')
            : t('paymentMethods.form.deleteError'),
        )
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
        case 'edit':
          openEdit(row)
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
    [openView, openEdit, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatusReorderToggle
              resource={PAYMENT_METHODS_DOMAIN}
              permission="payment-methods.update"
              labels={{
                openButton: t('paymentMethods.reorder.openButton'),
                title: t('paymentMethods.reorder.title'),
                subtitle: t('paymentMethods.reorder.subtitle'),
                dragHandleLabel: t('paymentMethods.reorder.dragHandleLabel'),
                loadError: t('paymentMethods.reorder.loadError'),
                saved: t('paymentMethods.reorder.saved'),
                forbidden: t('paymentMethods.reorder.forbidden'),
                genericError: t('paymentMethods.reorder.genericError'),
              }}
              onReordered={refreshGrid}
            />
            <Can permission="payment-methods.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('paymentMethods.form.newPaymentMethod')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={PAYMENT_METHODS_DOMAIN}
        renderers={paymentMethodColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={PAYMENT_METHODS_DOMAIN}
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
