/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchPurchaseOrderStatus, purchaseOrderStatusDetailQueryKey } from '@/features/purchase-order-statuses/api'
import { PurchaseOrderStatusDetailView } from '@/features/purchase-order-statuses/purchase-order-status-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDetailScreenProps, ModuleRegistryEntry } from '@/features/modules/types'
import { PURCHASE_ORDER_STATUSES_RESOURCE } from '@/features/purchase-order-statuses/types'

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

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: PURCHASE_ORDER_STATUSES_RESOURCE,
  basePath: `/${PURCHASE_ORDER_STATUSES_RESOURCE}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.purchaseOrderStatuses',
  DetailScreen: PurchaseOrderStatusDetailScreen,
  FormScreen: PurchaseOrderStatusFormScreen,
  // The detail IS the edit surface: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
}
