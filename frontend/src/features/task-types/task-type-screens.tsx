/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskType, taskTypeDetailQueryKey } from '@/features/task-types/api'
import { TaskTypeForm } from '@/features/task-types/task-type-form'
import { TaskTypeDetailView } from '@/features/task-types/task-type-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TaskTypeDetail } from '@/features/task-types/types'

/**
 * Content-only `task-types` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function TaskTypeDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskType,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(taskTypeDetailQueryKey(id), () => fetchTaskType(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('taskTypes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskType) {
    return <DetailLoading />
  }

  return <TaskTypeDetailView taskType={taskType} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a record being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function TaskTypeFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('taskTypes.form.leaveConfirm.title'),
    description: t('taskTypes.form.leaveConfirm.description'),
    confirmLabel: t('taskTypes.form.leaveConfirm.confirm'),
    cancelLabel: t('taskTypes.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: TaskTypeDetail) => {
    queryClient.invalidateQueries({ queryKey: taskTypeDetailQueryKey(saved.id) })
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
      <TaskTypeForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'task-types',
  basePath: '/task-types',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.taskTypes',
  DetailScreen: TaskTypeDetailScreen,
  FormScreen: TaskTypeFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
