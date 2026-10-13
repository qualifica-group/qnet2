/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchPurchaseRequestPriority, purchaseRequestPriorityDetailQueryKey } from '@/features/purchase-request-priorities/api'
import { PurchaseRequestPriorityForm } from '@/features/purchase-request-priorities/purchase-request-priority-form'
import { PurchaseRequestPriorityDetailView } from '@/features/purchase-request-priorities/purchase-request-priority-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { PurchaseRequestPriorityDetail } from '@/features/purchase-request-priorities/types'

/**
 * Content-only `purchase-request-priorities` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function PurchaseRequestPriorityDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: purchaseRequestPriority,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(purchaseRequestPriorityDetailQueryKey(id), () => fetchPurchaseRequestPriority(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('purchaseRequestPriorities.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !purchaseRequestPriority) {
    return <DetailLoading />
  }

  return <PurchaseRequestPriorityDetailView purchaseRequestPriority={purchaseRequestPriority} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a priority being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function PurchaseRequestPriorityFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('purchaseRequestPriorities.form.leaveConfirm.title'),
    description: t('purchaseRequestPriorities.form.leaveConfirm.description'),
    confirmLabel: t('purchaseRequestPriorities.form.leaveConfirm.confirm'),
    cancelLabel: t('purchaseRequestPriorities.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: PurchaseRequestPriorityDetail) => {
    queryClient.invalidateQueries({ queryKey: purchaseRequestPriorityDetailQueryKey(saved.id) })
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
      <PurchaseRequestPriorityForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'purchase-request-priorities',
  basePath: '/purchase-request-priorities',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.purchaseRequestPriorities',
  DetailScreen: PurchaseRequestPriorityDetailScreen,
  FormScreen: PurchaseRequestPriorityFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
