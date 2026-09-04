import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { TaskStatusesTable } from '@/features/task-statuses/task-statuses-table'

/**
 * Task statuses page (spec 0101). Light composition only: gates access with
 * `task-statuses.viewAny` and mounts the thin adapter, which in turn mounts the
 * generic table (`domain="task-statuses"`). The generic table owns config loading
 * and loading/error/empty states; no business logic or data fetching lives
 * here (mirrors `WorkOrdersPage`).
 */
export default function TaskStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="task-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('taskStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <TaskStatusesTable />
      </div>
    </Can>
  )
}
