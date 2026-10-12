/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProduct, productDetailQueryKey } from '@/features/products/api'
import { GuardedProductForm } from '@/features/products/guarded-product-form'
import { ProductDetailView } from '@/features/products/product-detail'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { ProductDetail } from '@/features/products/types'

/**
 * Content-only `products` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Unlike the
 * modal-native modules, `products` defaults to its bespoke dedicated pages
 * (spec 0022, `ProductDetailPage`/`ProductFormPage`) and does NOT get
 * generated routes (`generateRoutes: false`) — these screens only back the
 * 'modal' alternative a user can opt into (spec 0042).
 *
 * Spec 0218: the detail edits its fields in place, so `onEdit` is never used;
 * each save reports through `onChanged` (the modal host refreshes its grid and
 * keeps the record open).
 */
export function ProductDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: product,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(productDetailQueryKey(id), () => fetchProduct(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('products.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !product) {
    return <DetailLoading />
  }

  return <ProductDetailView product={product} onChanged={onChanged} />
}

/** Create only (spec 0218): there is no edit form, and no host asks for one. */
export function ProductFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: ProductDetail) => {
    queryClient.invalidateQueries({ queryKey: productDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  return <GuardedProductForm onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'products',
  basePath: '/products',
  defaultMode: OPEN_MODE_PAGE,
  generateRoutes: false,
  labelKey: 'navigation.products',
  DetailScreen: ProductDetailScreen,
  FormScreen: ProductFormScreen,
  // The detail IS the edit form (spec 0218): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
