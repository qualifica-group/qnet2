import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useTaskImportanceFormMeta } from '@/features/task-importances/use-task-importance-form-meta'
import { TaskImportanceFormBody } from '@/features/task-importances/task-importance-form-body'
import type { TaskImportanceDetail, TaskImportanceFormMode } from '@/features/task-importances/types'

interface TaskImportanceFormProps {
  mode: TaskImportanceFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (taskImportance: TaskImportanceDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a task importance.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/task-importances` — then hands off to `TaskImportanceFormBody`, which
 * reads every field from that context via `MetaField`/`useResourcePermissions()`.
 */
export function TaskImportanceForm(props: TaskImportanceFormProps) {
  const { t } = useTranslation()
  const meta = useTaskImportanceFormMeta(props.mode)

  if (meta.status === 'loading') {
    return (
      <div className="flex flex-col gap-4 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <TaskImportanceFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
