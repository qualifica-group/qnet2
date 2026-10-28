/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchWorkOrderWorkflow, workOrderWorkflowKeys } from '@/features/work-order-workflows/api'
import { WorkOrderWorkflowForm } from '@/features/work-order-workflows/work-order-workflow-form'
import { WorkOrderWorkflowDetailView } from '@/features/work-order-workflows/work-order-workflow-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { WorkOrderWorkflowDetail } from '@/features/work-order-workflows/types'

/**
 * Content-only `work-order-workflows` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. Reused as-is
 * by the modal Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`). The detail edits its rows in place,
 * so `onEdit` is never used; each save reports through `onChanged`.
 */
export function WorkOrderWorkflowDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: workOrderWorkflow,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(workOrderWorkflowKeys.detail(id), () => fetchWorkOrderWorkflow(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('workOrderWorkflows.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !workOrderWorkflow) {
    return <DetailLoading />
  }

  return <WorkOrderWorkflowDetailView workOrderWorkflow={workOrderWorkflow} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a workflow being created (Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload) always asks first.
 */
export function WorkOrderWorkflowFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('workOrderWorkflows.form.leaveConfirm.title'),
    description: t('workOrderWorkflows.form.leaveConfirm.description'),
    confirmLabel: t('workOrderWorkflows.form.leaveConfirm.confirm'),
    cancelLabel: t('workOrderWorkflows.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: WorkOrderWorkflowDetail) => {
    queryClient.invalidateQueries({ queryKey: workOrderWorkflowKeys.detail(saved.id) })
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
      <WorkOrderWorkflowForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'work-order-workflows',
  basePath: '/work-order-workflows',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.workOrderWorkflows',
  DetailScreen: WorkOrderWorkflowDetailScreen,
  FormScreen: WorkOrderWorkflowFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
