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
import { taskStatusColumnRenderers } from '@/features/task-statuses/column-renderers'
import { deleteTaskStatus } from '@/features/task-statuses/api'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'

/** Domain key used to mount the generic table for task statuses. */
const TASK_STATUSES_DOMAIN = 'task-statuses'

/**
 * Thin TaskStatuses adapter over the generic table. It mounts `<TableView>` with the
 * `task-statuses` domain, its custom cell renderers and a row-action handler, and
 * delegates the open mode (modal Sheet vs dedicated page) of view/edit/create
 * to `useModuleOpener`, resolved from the user's preference (spec 0042). It
 * still owns the delete flow, surfacing the backend's own message on the 409
 * "used by a task" guard (spec 0101 D-8b) and on the 422 system-row guard (D-8c),
 * and refreshes the SSRM grid after every mutation. Permission gating is an
 * affordance only; the backend re-authorizes each call.
 */
export function TaskStatusesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(TASK_STATUSES_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteTaskStatus(row.id)
        toast.success(t('taskStatuses.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('taskStatuses.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('taskStatuses.form.deleteForbidden'))
        } else if (status === 409 || status === 422) {
          // The row is still referenced by a Task, or is a system status. Surface the
          // backend's own message rather than a generic one.
          toast.error(error.response?.data?.message ?? t('taskStatuses.form.deleteInUse'))
        } else {
          toast.error(t('taskStatuses.form.deleteError'))
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
            <StatusReorderToggle
              resource={TASK_STATUSES_DOMAIN}
              permission="task-statuses.update"
              labels={{
                openButton: t('taskStatuses.reorder.openButton'),
                title: t('taskStatuses.reorder.title'),
                subtitle: t('taskStatuses.reorder.subtitle'),
                dragHandleLabel: t('taskStatuses.reorder.dragHandleLabel'),
                inactiveBadge: t('taskStatuses.reorder.inactiveBadge'),
                loadError: t('taskStatuses.reorder.loadError'),
                saved: t('taskStatuses.reorder.saved'),
                forbidden: t('taskStatuses.reorder.forbidden'),
                genericError: t('taskStatuses.reorder.genericError'),
              }}
              onReordered={refreshGrid}
            />
            <Can permission="task-statuses.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('taskStatuses.form.newTaskStatus')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={TASK_STATUSES_DOMAIN}
        renderers={taskStatusColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={TASK_STATUSES_DOMAIN}
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
