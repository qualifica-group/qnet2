import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RecordCard, RecordCardHeader } from '@/components/detail/record-panel'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useAbilities } from '@/features/auth/use-abilities'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import { taskColumnRenderers } from '@/features/tasks/task-column-renderers'
import { useTaskRowActions } from '@/features/tasks/use-task-row-actions'

interface WorkOrderTasksSectionProps {
  workOrderId: number
}

/**
 * The Commessa detail's Task panel, full width below the record like the
 * Offerte panel of the Opportunita' record: the SAME `TableView domain="tasks"`,
 * `taskColumnRenderers` and `useTaskRowActions` the standalone Task page uses,
 * scoped via `rowScope={{workOrderId}}` (the server ANDs it with the task
 * visibility scope). Row actions and "Nuovo task" are forced into a modal so
 * the Commessa is never abandoned; the create form arrives with this work
 * order prefilled and still editable. Mounted by `WorkOrderDetailView` only
 * with `tasks.viewAny`.
 */
export function WorkOrderTasksSection({ workOrderId }: WorkOrderTasksSectionProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [rowCount, setRowCount] = useState<number | null>(null)
  const handleRowCountChanged = useCallback((next: number | null) => setRowCount(next), [])

  const { handleAction, isBusy, activityRow, closeActivity, openCreateWith, sheet } = useTaskRowActions({
    onMutated: refreshGrid,
    forceMode: OPEN_MODE_MODAL,
  })

  const count = rowCount ?? 0

  return (
    <RecordCard>
      <RecordCardHeader
        title={
          <span className="flex items-center gap-2">
            <span className="truncate">{t('workOrders.detail.tasks.title')}</span>
            <Badge variant="secondary" aria-label={t('workOrders.detail.tasks.countLabel', { count })}>
              {count}
            </Badge>
          </span>
        }
        actions={
          can('tasks.create') ? (
            <Button size="sm" onClick={() => openCreateWith({ work_order_id: workOrderId })}>
              <Plus aria-hidden="true" />
              {t('tasks.form.newTask')}
            </Button>
          ) : null
        }
      />

      <div className="min-w-0 p-4">
        <TableView
          ref={tableRef}
          domain={TASKS_DOMAIN}
          rowScope={{ workOrderId }}
          renderers={taskColumnRenderers}
          onAction={handleAction}
          isBusy={isBusy}
          onRowCountChanged={handleRowCountChanged}
        />
      </div>

      {sheet}

      <ResourceActivityDialog resource={TASKS_DOMAIN} row={activityRow} onOpenChange={closeActivity} />
    </RecordCard>
  )
}
