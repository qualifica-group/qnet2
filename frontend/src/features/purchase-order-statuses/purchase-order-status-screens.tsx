/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchPurchaseOrderStatus, purchaseOrderStatusDetailQueryKey } from '@/features/purchase-order-statuses/api'
import { PurchaseOrderStatusDetailView } from '@/features/purchase-order-statuses/purchase-order-status-detail'
import type { ModuleDetailScreenProps, ModuleScreens } from '@/features/modules/types'

/**
 * Content-only `purchase-order-statuses` detail for the module registry (spec
 * 0042): fetch + the presentational view. The rows edit in place, each save
 * reporting through `onChanged`.
 */
export function PurchaseOrderStatusDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: status,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(purchaseOrderStatusDetailQueryKey(id), () => fetchPurchaseOrderStatus(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('purchaseOrderStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !status) {
    return <DetailLoading />
  }

  return <PurchaseOrderStatusDetailView status={status} onChanged={onChanged} />
}

/** The catalog has no creation (spec 0248 D-2): the registry still needs a form screen, which renders nothing. */
export function PurchaseOrderStatusFormScreen() {
  return null
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: PurchaseOrderStatusDetailScreen,
  FormScreen: PurchaseOrderStatusFormScreen,
}
