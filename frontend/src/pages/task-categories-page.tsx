import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { TaskCategoriesTable } from '@/features/task-categories/task-categories-table'

/**
 * Task categories page (spec 0101). Light composition only: gates access with
 * `task-categories.viewAny` and mounts the thin adapter, which in turn mounts the
 * generic table (`domain="task-categories"`). The generic table owns config loading
 * and loading/error/empty states; no business logic or data fetching lives
 * here (mirrors `WorkOrdersPage`).
 */
export default function TaskCategoriesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="task-categories.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('taskCategories.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <TaskCategoriesTable />
      </div>
    </Can>
  )
}
