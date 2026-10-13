/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchWorkOrderPaymentStatus, workOrderPaymentStatusDetailQueryKey } from '@/features/work-order-payment-statuses/api'
import { WorkOrderPaymentStatusForm } from '@/features/work-order-payment-statuses/work-order-payment-status-form'
import { WorkOrderPaymentStatusDetailView } from '@/features/work-order-payment-statuses/work-order-payment-status-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { WorkOrderPaymentStatusDetail } from '@/features/work-order-payment-statuses/types'

/**
 * Content-only `work-order-payment-statuses` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function WorkOrderPaymentStatusDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: workOrderPaymentStatus,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(workOrderPaymentStatusDetailQueryKey(id), () => fetchWorkOrderPaymentStatus(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('workOrderPaymentStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !workOrderPaymentStatus) {
    return <DetailLoading />
  }

  return <WorkOrderPaymentStatusDetailView workOrderPaymentStatus={workOrderPaymentStatus} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a record being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function WorkOrderPaymentStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('workOrderPaymentStatuses.form.leaveConfirm.title'),
    description: t('workOrderPaymentStatuses.form.leaveConfirm.description'),
    confirmLabel: t('workOrderPaymentStatuses.form.leaveConfirm.confirm'),
    cancelLabel: t('workOrderPaymentStatuses.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: WorkOrderPaymentStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: workOrderPaymentStatusDetailQueryKey(saved.id) })
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
      <WorkOrderPaymentStatusForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'work-order-payment-statuses',
  basePath: '/work-order-payment-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.workOrderPaymentStatuses',
  DetailScreen: WorkOrderPaymentStatusDetailScreen,
  FormScreen: WorkOrderPaymentStatusFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
