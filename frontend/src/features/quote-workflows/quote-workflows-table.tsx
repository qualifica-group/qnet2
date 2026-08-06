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
import { quoteWorkflowColumnRenderers } from '@/features/quote-workflows/column-renderers'
import { deleteQuoteWorkflow } from '@/features/quote-workflows/api'
import { DefaultStatusesToggle } from '@/features/quote-workflows/default-statuses-toggle'

/** Domain key used to mount the generic table for opportunity workflows. */
const OPPORTUNITY_WORKFLOWS_DOMAIN = 'quote-workflows'

/**
 * Thin Opportunity Workflows adapter over the generic table (spec 0047 Lane
 * C, AC-023). It mounts `<TableView>` with the `quote-workflows`
 * domain, its custom cell renderers and a row-action handler, and delegates
 * the open mode (modal Sheet vs dedicated page) of view/edit/create to
 * `useModuleOpener`. It still owns the delete flow (DELETE re-resolves every
 * impacted Opportunity server-side, AC-018) and refreshing the SSRM grid
 * after every mutation via the table's imperative handle. Permission gating
 * is an affordance only; the backend re-authorizes each call.
 */
export function QuoteWorkflowsTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(OPPORTUNITY_WORKFLOWS_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteQuoteWorkflow(row.id)
        toast.success(t('quoteWorkflows.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('quoteWorkflows.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('quoteWorkflows.form.deleteForbidden'))
        } else {
          toast.error(error.response?.data?.message ?? t('quoteWorkflows.form.deleteError'))
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
            <DefaultStatusesToggle />
            <Can permission="quote-workflows.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('quoteWorkflows.form.newQuoteWorkflow')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={OPPORTUNITY_WORKFLOWS_DOMAIN}
        renderers={quoteWorkflowColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={OPPORTUNITY_WORKFLOWS_DOMAIN}
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
