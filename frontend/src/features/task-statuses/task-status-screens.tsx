/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskStatus, taskStatusDetailQueryKey } from '@/features/task-statuses/api'
import { TaskStatusForm } from '@/features/task-statuses/task-status-form'
import { TaskStatusDetailView } from '@/features/task-statuses/task-status-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TaskStatusDetail } from '@/features/task-statuses/types'

/**
 * Content-only `task-statuses` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function TaskStatusDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskStatus,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(taskStatusDetailQueryKey(id), () => fetchTaskStatus(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('taskStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskStatus) {
    return <DetailLoading />
  }

  return <TaskStatusDetailView taskStatus={taskStatus} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a record being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function TaskStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('taskStatuses.form.leaveConfirm.title'),
    description: t('taskStatuses.form.leaveConfirm.description'),
    confirmLabel: t('taskStatuses.form.leaveConfirm.confirm'),
    cancelLabel: t('taskStatuses.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: TaskStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: taskStatusDetailQueryKey(saved.id) })
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
      <TaskStatusForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'task-statuses',
  basePath: '/task-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.taskStatuses',
  DetailScreen: TaskStatusDetailScreen,
  FormScreen: TaskStatusFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
