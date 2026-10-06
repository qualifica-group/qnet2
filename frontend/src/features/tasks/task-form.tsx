import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { TaskFormBody } from '@/features/tasks/task-form-body'
import { useTaskFormMeta } from '@/features/tasks/use-task-form-meta'
import type { TaskCreateFormMode, TaskDetail } from '@/features/tasks/types'

interface TaskFormProps {
  mode: TaskCreateFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (task: TaskDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * RHF + Zod form used for creating (or duplicating) a task — editing happens
 * in place on the detail (spec 0195). Metadata-driven (spec 0004): resolves
 * the create-context `ResourcePermissions` (`GET /meta/tasks`) before
 * rendering, so every field's visibility/editability is decided server-side,
 * never here.
 */
export function TaskForm({ mode, onSuccess, onCancel }: TaskFormProps) {
  const { t } = useTranslation()
  const meta = useTaskFormMeta()

  if (meta.status === 'loading') {
    return <RecordFormSkeleton />
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
      <TaskFormBody mode={mode} onSuccess={onSuccess} onCancel={onCancel} />
    </ResourcePermissionsProvider>
  )
}
