/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskTemplate, taskTemplateDetailQueryKey } from '@/features/task-templates/api'
import { TaskTemplateForm } from '@/features/task-templates/task-template-form'
import { TaskTemplateDetailView } from '@/features/task-templates/task-template-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TaskTemplateDetail } from '@/features/task-templates/types'

/**
 * Content-only `task-templates` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. Reused as-is by the
 * modal Sheet (`useModuleOpener`) and by the generic dedicated pages.
 *
 * Spec 0227: the detail edits its rows in place, so `onEdit` is never used;
 * each save reports through `onChanged`.
 */
export function TaskTemplateDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskTemplate,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(taskTemplateDetailQueryKey(id), () => fetchTaskTemplate(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('taskTemplates.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskTemplate) {
    return <DetailLoading />
  }

  return <TaskTemplateDetailView taskTemplate={taskTemplate} onChanged={onChanged} />
}

/**
 * Create only (spec 0227): there is no edit form, and the registry generates
 * no `:id/edit` route. Leaving a template being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function TaskTemplateFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('taskTemplates.form.leaveConfirm.title'),
    description: t('taskTemplates.form.leaveConfirm.description'),
    confirmLabel: t('taskTemplates.form.leaveConfirm.confirm'),
    cancelLabel: t('taskTemplates.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: TaskTemplateDetail) => {
    queryClient.invalidateQueries({ queryKey: taskTemplateDetailQueryKey(saved.id) })
    // Saved: the navigation to the new template's detail is no "leaving".
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
      <TaskTemplateForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'task-templates',
  basePath: '/task-templates',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.taskTemplates',
  DetailScreen: TaskTemplateDetailScreen,
  FormScreen: TaskTemplateFormScreen,
  // The detail IS the edit form (spec 0227): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
