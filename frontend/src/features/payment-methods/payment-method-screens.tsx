/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchPaymentMethod, paymentMethodDetailQueryKey } from '@/features/payment-methods/api'
import { PaymentMethodForm } from '@/features/payment-methods/payment-method-form'
import { PaymentMethodDetailView } from '@/features/payment-methods/payment-method-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { PaymentMethodDetail } from '@/features/payment-methods/types'

/**
 * Content-only `payment-methods` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. The detail
 * edits its rows in place, so `onEdit` is never used; each save reports
 * through `onChanged`.
 */
export function PaymentMethodDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: paymentMethod,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(paymentMethodDetailQueryKey(id), () => fetchPaymentMethod(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('paymentMethods.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !paymentMethod) {
    return <DetailLoading />
  }

  return <PaymentMethodDetailView paymentMethod={paymentMethod} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a payment method being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function PaymentMethodFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('paymentMethods.form.leaveConfirm.title'),
    description: t('paymentMethods.form.leaveConfirm.description'),
    confirmLabel: t('paymentMethods.form.leaveConfirm.confirm'),
    cancelLabel: t('paymentMethods.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: PaymentMethodDetail) => {
    queryClient.invalidateQueries({ queryKey: paymentMethodDetailQueryKey(saved.id) })
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
      <PaymentMethodForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'payment-methods',
  basePath: '/payment-methods',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.paymentMethods',
  DetailScreen: PaymentMethodDetailScreen,
  FormScreen: PaymentMethodFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
