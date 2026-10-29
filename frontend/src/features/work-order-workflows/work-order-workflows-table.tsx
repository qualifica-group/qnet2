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
import { workOrderWorkflowColumnRenderers } from '@/features/work-order-workflows/column-renderers'
import { deleteWorkOrderWorkflow } from '@/features/work-order-workflows/api'
import { WorkOrderDefaultStatusesSheet } from '@/features/work-order-workflows/work-order-default-statuses-sheet'
import { DefaultStatusesToggle } from '@/features/workflow-statuses/default-statuses-toggle'
import { forbiddenMessage } from '@/lib/forbidden-message'

/** Domain key used to mount the generic table for work order workflows. */
const WORK_ORDER_WORKFLOWS_DOMAIN = 'work-order-workflows'

/**
 * Thin Work Order Workflows adapter over the generic table (spec 0322). It mounts `<TableView>` with the `work-order-workflows`
 * domain, its custom cell renderers and a row-action handler, and delegates
 * the open mode (modal Sheet vs dedicated page) of view/edit/create to
 * `useModuleOpener`. It still owns the delete flow (DELETE re-resolves every
 * impacted work order server-side) and refreshing the SSRM grid
 * after every mutation via the table's imperative handle. Permission gating
 * is an affordance only; the backend re-authorizes each call.
 */
export function WorkOrderWorkflowsTable() {
  const { t, i18n } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(WORK_ORDER_WORKFLOWS_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteWorkOrderWorkflow(Number(row.id))
        toast.success(t('workOrderWorkflows.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('workOrderWorkflows.form.deleteError'))
          return
        }
        const forbidden = forbiddenMessage(error, i18n)
        if (forbidden !== null) {
          toast.error(forbidden)
        } else {
          toast.error(error.response?.data?.message ?? t('workOrderWorkflows.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t, i18n],
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
            <DefaultStatusesToggle
              permission="work-order-workflows.update"
              label={t('workOrderWorkflows.defaultStatuses.openButton')}
              renderSheet={(sheet) => <WorkOrderDefaultStatusesSheet {...sheet} />}
            />
            <Can permission="work-order-workflows.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('workOrderWorkflows.form.newWorkOrderWorkflow')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={WORK_ORDER_WORKFLOWS_DOMAIN}
        renderers={workOrderWorkflowColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={WORK_ORDER_WORKFLOWS_DOMAIN}
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
