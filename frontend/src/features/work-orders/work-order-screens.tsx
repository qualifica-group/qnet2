/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { fetchWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { WorkOrderForm } from '@/features/work-orders/work-order-form'
import { parseEntityId } from '@/routes/entity-id'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { WorkOrderDetail } from '@/features/work-orders/types'

/**
 * Content-only `work-orders` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused as-is
 * by the generic dedicated pages (`ModuleDetailPage`/`ModuleFormPage`) and by
 * the modal Sheet (`useModuleOpener`), whichever the user's preference picks.
 *
 * Spec 0195 applied to Commesse (user directive 2026-10-06): the detail edits
 * its fields in place, so `onEdit` is never used; each save reports through
 * `onChanged` (the modal host refreshes its grid and keeps the record open).
 */
export function WorkOrderDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: workOrder,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(workOrderDetailQueryKey(id), () => fetchWorkOrder(id))

  if (isError) {
    return (
      <DetailError
        message={t('workOrders.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !workOrder) {
    return <DetailLoading />
  }

  return <WorkOrderDetailView workOrder={workOrder} onChanged={onChanged} />
}

export function WorkOrderFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  // Leaving a work order being created — Cancel, the Sheet's X/overlay/Esc,
  // a link, a reload — always asks first (spec 0195 D-9 applied to Commesse).
  const leaveGuard = useFormLeaveGuard({
    title: t('workOrders.form.leaveConfirm.title'),
    description: t('workOrders.form.leaveConfirm.description'),
    confirmLabel: t('workOrders.form.leaveConfirm.confirm'),
    cancelLabel: t('workOrders.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  const handleSuccess = (saved: WorkOrderDetail) => {
    queryClient.invalidateQueries({ queryKey: workOrderDetailQueryKey(saved.id) })
    // Saved: the navigation to the new work order's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = async () => {
    if (await leaveGuard.confirmLeave()) {
      onCancel()
    }
  }

  // No edit form: the detail edits in place, and the registry generates no
  // `:id/edit` route (`generateEditRoute: false`).
  if (mode.type !== 'create') {
    return null
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <WorkOrderForm
        registryId={parseEntityId(String(mode.params?.registry_id ?? ''))}
        onSuccess={handleSuccess}
        onCancel={() => void handleCancel()}
      />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'work-orders',
  basePath: '/work-orders',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.workOrders',
  DetailScreen: WorkOrderDetailScreen,
  FormScreen: WorkOrderFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
}
