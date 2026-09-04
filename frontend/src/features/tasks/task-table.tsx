import { useCallback, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import { taskColumnRenderers } from '@/features/tasks/task-column-renderers'
import { useTaskRowActions } from '@/features/tasks/use-task-row-actions'

/**
 * Thin `tasks` adapter over the generic table (AC-070): search, sorting,
 * server paging, column selection, saved preferences and export all come from
 * the generic engine — no dedicated endpoint. Row behaviour is delegated to
 * `useTaskRowActions`. Permission gating here is an affordance only; the
 * backend re-authorizes each call and additionally restricts the rows by the
 * visibility scope (D-9).
 */
export function TasksTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const { handleAction, isBusy, activityRow, closeActivity, openCreate, sheet } = useTaskRowActions({
    onMutated: refreshGrid,
  })

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="tasks.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('tasks.form.newTask')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={TASKS_DOMAIN}
        renderers={taskColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog resource={TASKS_DOMAIN} row={activityRow} onOpenChange={closeActivity} />
    </div>
  )
}
