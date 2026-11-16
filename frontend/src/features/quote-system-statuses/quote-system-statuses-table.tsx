import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { refusedActionMessage } from '@/lib/forbidden-message'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { quoteSystemStatusColumnRenderers } from '@/features/quote-system-statuses/column-renderers'
import { deleteQuoteSystemStatus } from '@/features/quote-system-statuses/api'
import { forSelectKeys } from '@/features/for-select/query-keys'
import { QUOTE_SYSTEM_STATUSES_FOR_SELECT_RESOURCE } from '@/features/quote-system-statuses/use-quote-system-status-options'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'

/** Domain key used to mount the generic table for quote system statuses. */
const QUOTE_SYSTEM_STATUSES_DOMAIN = 'quote-system-statuses'

/**
 * Thin Quote System Statuses adapter over the generic table. It mounts
 * `<TableView>` with the `quote-system-statuses` domain, its custom cell
 * renderers and a row-action handler, and delegates the open mode (modal
 * Sheet vs dedicated page) of view/edit/create to `useModuleOpener`,
 * resolved from the user's preference (spec 0042). It still owns the delete
 * flow (confirming + running the delete mutation, surfacing the backend's
 * exact 409 message when the status is still referenced by a working status) and
 * refreshing the SSRM grid after every mutation via the table's imperative
 * handle. Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export function QuoteSystemStatusesTable() {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(QUOTE_SYSTEM_STATUSES_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteQuoteSystemStatus(Number(row.id))
        toast.success(t('quoteSystemStatuses.form.deleted'))
        void queryClient.invalidateQueries({ queryKey: forSelectKeys.resource(QUOTE_SYSTEM_STATUSES_FOR_SELECT_RESOURCE) })
        refreshGrid()
      } catch (error) {
        toast.error(refusedActionMessage(error, t('quoteSystemStatuses.form.deleteError'), i18n))
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, queryClient, t, i18n],
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
              resource={QUOTE_SYSTEM_STATUSES_DOMAIN}
              permission="quote-system-statuses.update"
              labels={{
                openButton: t('quoteSystemStatuses.reorder.openButton'),
                title: t('quoteSystemStatuses.reorder.title'),
                subtitle: t('quoteSystemStatuses.reorder.subtitle'),
                dragHandleLabel: t('quoteSystemStatuses.reorder.dragHandleLabel'),
                loadError: t('quoteSystemStatuses.reorder.loadError'),
                saved: t('quoteSystemStatuses.reorder.saved'),
                genericError: t('quoteSystemStatuses.reorder.genericError'),
              }}
              onReordered={refreshGrid}
            />
            <Can permission="quote-system-statuses.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('quoteSystemStatuses.form.newQuoteSystemStatus')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={QUOTE_SYSTEM_STATUSES_DOMAIN}
        renderers={quoteSystemStatusColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={QUOTE_SYSTEM_STATUSES_DOMAIN}
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
