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
import { invoiceTagColumnRenderers } from '@/features/invoice-tags/column-renderers'
import { deleteInvoiceTag } from '@/features/invoice-tags/api'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'

/** Domain key used to mount the generic table for invoice tags. */
const INVOICE_TAGS_DOMAIN = 'invoice-tags'

/**
 * Thin InvoiceTags adapter over the generic table. It mounts `<TableView>` with the
 * `invoice-tags` domain, its custom cell renderers and a row-action handler, and
 * delegates the open mode (modal Sheet vs dedicated page) of view/edit/create
 * to `useModuleOpener`, resolved from the user's preference (spec 0042). It
 * still owns the delete flow, surfacing the backend's own message on the 409
 * "used by an invoice" guard (spec 0247 D-5), and refreshes the SSRM grid after
 * every mutation. The reorder toggle reuses the shared `StatusReorderToggle`
 * (spec 0247): this lookup has no system
 * rows, so every row is draggable and `ordered_ids` carries the whole table —
 * which is exactly what the shared sheet already sends when no option reports
 * a `meta.system_key`. Permission gating is an affordance only; the backend
 * re-authorizes each call.
 */
export function InvoiceTagsTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(INVOICE_TAGS_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteInvoiceTag(Number(row.id))
        toast.success(t('invoiceTags.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('invoiceTags.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('invoiceTags.form.deleteForbidden'))
        } else if (status === 409) {
          // The row is still referenced by an invoice. Surface the
          // backend's own message rather than a generic one.
          toast.error(error.response?.data?.message ?? t('invoiceTags.form.deleteInUse'))
        } else {
          toast.error(t('invoiceTags.form.deleteError'))
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
              resource={INVOICE_TAGS_DOMAIN}
              permission="invoice-tags.update"
              labels={{
                openButton: t('invoiceTags.reorder.openButton'),
                title: t('invoiceTags.reorder.title'),
                subtitle: t('invoiceTags.reorder.subtitle'),
                dragHandleLabel: t('invoiceTags.reorder.dragHandleLabel'),
                inactiveBadge: t('invoiceTags.reorder.inactiveBadge'),
                loadError: t('invoiceTags.reorder.loadError'),
                saved: t('invoiceTags.reorder.saved'),
                forbidden: t('invoiceTags.reorder.forbidden'),
                genericError: t('invoiceTags.reorder.genericError'),
              }}
              onReordered={refreshGrid}
            />
            <Can permission="invoice-tags.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('invoiceTags.form.newInvoiceTag')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={INVOICE_TAGS_DOMAIN}
        renderers={invoiceTagColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={INVOICE_TAGS_DOMAIN}
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
