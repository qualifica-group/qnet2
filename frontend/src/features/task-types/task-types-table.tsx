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
import { taskTypeColumnRenderers } from '@/features/task-types/column-renderers'
import { deleteTaskType } from '@/features/task-types/api'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'

/** Domain key used to mount the generic table for task types. */
const TASK_TYPES_DOMAIN = 'task-types'

/**
 * Thin TaskTypes adapter over the generic table. It mounts `<TableView>` with the
 * `task-types` domain, its custom cell renderers and a row-action handler, and
 * delegates the open mode (modal Sheet vs dedicated page) of view/edit/create
 * to `useModuleOpener`, resolved from the user's preference (spec 0042). It
 * still owns the delete flow, surfacing the backend's own message on the 409
 * "used by a task" guard (spec 0101 D-8b), and refreshes the SSRM grid after
 * every mutation. The reorder toggle reuses the shared `StatusReorderToggle`
 * (spec 0101 D-4, rectified): unlike `task-statuses` this lookup has no system
 * rows, so every row is draggable and `ordered_ids` carries the whole table —
 * which is exactly what the shared sheet already sends when no option reports
 * a `meta.system_key`. Permission gating is an affordance only; the backend
 * re-authorizes each call.
 */
export function TaskTypesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(TASK_TYPES_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteTaskType(row.id)
        toast.success(t('taskTypes.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('taskTypes.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('taskTypes.form.deleteForbidden'))
        } else if (status === 409) {
          // The row is still referenced by a Task. Surface the
          // backend's own message rather than a generic one.
          toast.error(error.response?.data?.message ?? t('taskTypes.form.deleteInUse'))
        } else {
          toast.error(t('taskTypes.form.deleteError'))
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
              resource={TASK_TYPES_DOMAIN}
              permission="task-types.update"
              labels={{
                openButton: t('taskTypes.reorder.openButton'),
                title: t('taskTypes.reorder.title'),
                subtitle: t('taskTypes.reorder.subtitle'),
                dragHandleLabel: t('taskTypes.reorder.dragHandleLabel'),
                inactiveBadge: t('taskTypes.reorder.inactiveBadge'),
                loadError: t('taskTypes.reorder.loadError'),
                saved: t('taskTypes.reorder.saved'),
                forbidden: t('taskTypes.reorder.forbidden'),
                genericError: t('taskTypes.reorder.genericError'),
              }}
              onReordered={refreshGrid}
            />
            <Can permission="task-types.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('taskTypes.form.newTaskType')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={TASK_TYPES_DOMAIN}
        renderers={taskTypeColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={TASK_TYPES_DOMAIN}
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
