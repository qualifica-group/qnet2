import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { TasksTable } from '@/features/tasks/task-table'

/**
 * Tasks page (spec 0101). Light composition only: gates access with
 * `tasks.viewAny` and mounts the thin adapter, which in turn mounts the
 * generic table (`domain="tasks"`). The generic table owns config loading
 * and loading/error/empty states; no business logic or data fetching lives
 * here (mirrors `WorkOrdersPage`).
 */
export default function TasksPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="tasks.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('tasks.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <TasksTable />
      </div>
    </Can>
  )
}
