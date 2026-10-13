import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { TaskCategoryFormBody } from '@/features/task-categories/task-category-form-body'
import type { TaskCategoryDetail } from '@/features/task-categories/types'

interface TaskCategoryFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (taskCategory: TaskCategoryDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The task category create form (there is no edit form: the detail edits in
 * place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/task-categories`) before rendering
 * `TaskCategoryFormBody`.
 */
export function TaskCategoryForm(props: TaskCategoryFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('task-categories')

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (!metaQuery.data) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <TaskCategoryFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
