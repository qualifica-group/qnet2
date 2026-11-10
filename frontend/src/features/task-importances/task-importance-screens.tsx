/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskImportance, taskImportanceDetailQueryKey } from '@/features/task-importances/api'
import { TaskImportanceForm } from '@/features/task-importances/task-importance-form'
import { TaskImportanceDetailView } from '@/features/task-importances/task-importance-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleScreens,
} from '@/features/modules/types'
import type { TaskImportanceDetail } from '@/features/task-importances/types'

/**
 * Content-only `task-importances` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function TaskImportanceDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskImportance,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(taskImportanceDetailQueryKey(id), () => fetchTaskImportance(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('taskImportances.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskImportance) {
    return <DetailLoading />
  }

  return <TaskImportanceDetailView taskImportance={taskImportance} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a record being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function TaskImportanceFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('taskImportances.form.leaveConfirm.title'),
    description: t('taskImportances.form.leaveConfirm.description'),
    confirmLabel: t('taskImportances.form.leaveConfirm.confirm'),
    cancelLabel: t('taskImportances.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: TaskImportanceDetail) => {
    queryClient.invalidateQueries({ queryKey: taskImportanceDetailQueryKey(saved.id) })
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
      <TaskImportanceForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: TaskImportanceDetailScreen,
  FormScreen: TaskImportanceFormScreen,
}
