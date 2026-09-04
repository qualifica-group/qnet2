import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { TaskPrioritiesTable } from '@/features/task-priorities/task-priorities-table'

/**
 * Task priorities page (spec 0101). Light composition only: gates access with
 * `task-priorities.viewAny` and mounts the thin adapter, which in turn mounts the
 * generic table (`domain="task-priorities"`). The generic table owns config loading
 * and loading/error/empty states; no business logic or data fetching lives
 * here (mirrors `WorkOrdersPage`).
 */
export default function TaskPrioritiesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="task-priorities.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('taskPriorities.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <TaskPrioritiesTable />
      </div>
    </Can>
  )
}
