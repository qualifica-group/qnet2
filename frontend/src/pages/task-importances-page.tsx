import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { TaskImportancesTable } from '@/features/task-importances/task-importances-table'

/**
 * Task importances page (spec 0101). Light composition only: gates access with
 * `task-importances.viewAny` and mounts the thin adapter, which in turn mounts the
 * generic table (`domain="task-importances"`). The generic table owns config loading
 * and loading/error/empty states; no business logic or data fetching lives
 * here (mirrors `WorkOrdersPage`).
 */
export default function TaskImportancesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="task-importances.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('taskImportances.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <TaskImportancesTable />
      </div>
    </Can>
  )
}
