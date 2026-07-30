/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchPaymentMethod } from '@/features/payment-methods/api'
import { PaymentMethodForm } from '@/features/payment-methods/payment-method-form'
import { PaymentMethodDetailView } from '@/features/payment-methods/payment-method-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { PaymentMethodDetail } from '@/features/payment-methods/types'

/** Query key for a single payment method's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['payment-methods', 'detail', id] as const
}

/**
 * Content-only `payment-methods` screens for the module registry (spec
 * 0068): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function PaymentMethodDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: paymentMethod,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchPaymentMethod(id))

  if (isError) {
    return (
      <DetailError
        message={t('paymentMethods.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !paymentMethod) {
    return <DetailLoading />
  }

  return <PaymentMethodDetailView paymentMethod={paymentMethod} />
}

export function PaymentMethodFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: PaymentMethodDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <PaymentMethodForm
        mode={{ type: 'create' }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <PaymentMethodEditScreen
      paymentMethodId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface PaymentMethodEditScreenProps {
  paymentMethodId: number
  onSuccess: (paymentMethod: PaymentMethodDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized payment method detail before mounting
 * the edit form, so the partial PATCH starts from authoritative values
 * rather than a stale snapshot.
 */
function PaymentMethodEditScreen({
  paymentMethodId,
  onSuccess,
  onCancel,
}: PaymentMethodEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: paymentMethod,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(paymentMethodId), () => fetchPaymentMethod(paymentMethodId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('paymentMethods.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !paymentMethod) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <PaymentMethodForm
      mode={{ type: 'edit', paymentMethod }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
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
}
