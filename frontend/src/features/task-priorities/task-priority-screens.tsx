/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskPriority, taskPriorityDetailQueryKey } from '@/features/task-priorities/api'
import { TaskPriorityForm } from '@/features/task-priorities/task-priority-form'
import { TaskPriorityDetailView } from '@/features/task-priorities/task-priority-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TaskPriorityDetail } from '@/features/task-priorities/types'

/**
 * Content-only `task-priorities` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function TaskPriorityDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskPriority,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(taskPriorityDetailQueryKey(id), () => fetchTaskPriority(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('taskPriorities.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskPriority) {
    return <DetailLoading />
  }

  return <TaskPriorityDetailView taskPriority={taskPriority} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a priority being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function TaskPriorityFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('taskPriorities.form.leaveConfirm.title'),
    description: t('taskPriorities.form.leaveConfirm.description'),
    confirmLabel: t('taskPriorities.form.leaveConfirm.confirm'),
    cancelLabel: t('taskPriorities.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: TaskPriorityDetail) => {
    queryClient.invalidateQueries({ queryKey: taskPriorityDetailQueryKey(saved.id) })
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
      <TaskPriorityForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'task-priorities',
  basePath: '/task-priorities',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.taskPriorities',
  DetailScreen: TaskPriorityDetailScreen,
  FormScreen: TaskPriorityFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
