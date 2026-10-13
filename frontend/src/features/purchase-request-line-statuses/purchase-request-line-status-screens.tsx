/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import {
  fetchPurchaseRequestLineStatus,
  purchaseRequestLineStatusDetailQueryKey,
} from '@/features/purchase-request-line-statuses/api'
import { PurchaseRequestLineStatusDetailView } from '@/features/purchase-request-line-statuses/purchase-request-line-status-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDetailScreenProps, ModuleRegistryEntry } from '@/features/modules/types'

/**
 * Content-only `purchase-request-line-statuses` screens for the module
 * registry (spec 0042). The detail edits its rows in place; each save reports
 * through `onChanged`.
 */
export function PurchaseRequestLineStatusDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: purchaseRequestLineStatus,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(purchaseRequestLineStatusDetailQueryKey(id), () => fetchPurchaseRequestLineStatus(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('purchaseRequestLineStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !purchaseRequestLineStatus) {
    return <DetailLoading />
  }

  return <PurchaseRequestLineStatusDetailView purchaseRequestLineStatus={purchaseRequestLineStatus} onChanged={onChanged} />
}

/** The catalog has no creation: the registry still requires a form screen, which never renders anything. */
export function PurchaseRequestLineStatusFormScreen() {
  return null
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'purchase-request-line-statuses',
  basePath: '/purchase-request-line-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.purchaseRequestLineStatuses',
  DetailScreen: PurchaseRequestLineStatusDetailScreen,
  FormScreen: PurchaseRequestLineStatusFormScreen,
  // The detail IS the edit surface: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
}
