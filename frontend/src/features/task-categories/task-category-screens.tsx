/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskCategory, taskCategoryDetailQueryKey } from '@/features/task-categories/api'
import { TaskCategoryForm } from '@/features/task-categories/task-category-form'
import { TaskCategoryDetailView } from '@/features/task-categories/task-category-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TaskCategoryDetail } from '@/features/task-categories/types'

/**
 * Content-only `task-categories` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function TaskCategoryDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskCategory,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(taskCategoryDetailQueryKey(id), () => fetchTaskCategory(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('taskCategories.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskCategory) {
    return <DetailLoading />
  }

  return <TaskCategoryDetailView taskCategory={taskCategory} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a record being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function TaskCategoryFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('taskCategories.form.leaveConfirm.title'),
    description: t('taskCategories.form.leaveConfirm.description'),
    confirmLabel: t('taskCategories.form.leaveConfirm.confirm'),
    cancelLabel: t('taskCategories.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: TaskCategoryDetail) => {
    queryClient.invalidateQueries({ queryKey: taskCategoryDetailQueryKey(saved.id) })
    // Saved: the navigation to the new record's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = () => {
    void leaveGuard.confirmLeave().then((leave) => {
      if (leave) {
        onCancel()
      }
    })
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <TaskCategoryForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'task-categories',
  basePath: '/task-categories',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.taskCategories',
  DetailScreen: TaskCategoryDetailScreen,
  FormScreen: TaskCategoryFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
