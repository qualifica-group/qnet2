import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { TaskTemplatesTable } from '@/features/task-templates/task-templates-table'

/**
 * Task templates page. Light composition only: gates access with
 * `task-templates.viewAny` and mounts the thin task-templates adapter, which
 * in turn mounts the generic table (`domain="task-templates"`). The generic
 * table owns config loading and loading/error/empty states; no business
 * logic or data fetching lives here (mirrors `ProductTypologiesPage`).
 */
export default function TaskTemplatesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="task-templates.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('taskTemplates.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <TaskTemplatesTable />
      </div>
    </Can>
  )
}
