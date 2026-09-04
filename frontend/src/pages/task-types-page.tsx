import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { TaskTypesTable } from '@/features/task-types/task-types-table'

/**
 * Task types page (spec 0101). Light composition only: gates access with
 * `task-types.viewAny` and mounts the thin adapter, which in turn mounts the
 * generic table (`domain="task-types"`). The generic table owns config loading
 * and loading/error/empty states; no business logic or data fetching lives
 * here (mirrors `WorkOrdersPage`).
 */
export default function TaskTypesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="task-types.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('taskTypes.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <TaskTypesTable />
      </div>
    </Can>
  )
}
