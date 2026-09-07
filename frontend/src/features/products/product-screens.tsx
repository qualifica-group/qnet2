/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProduct, productDetailQueryKey } from '@/features/products/api'
import { ProductForm } from '@/features/products/product-form'
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
 */
export function ProductDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: product,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(productDetailQueryKey(id), () => fetchProduct(id))

  if (isError) {
    return (
      <DetailError
        message={t('products.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !product) {
    return <DetailLoading />
  }

  return <ProductDetailView product={product} />
}

export function ProductFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: ProductDetail) => {
    queryClient.invalidateQueries({ queryKey: productDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <ProductForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <ProductEditScreen productId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface ProductEditScreenProps {
  productId: number
  onSuccess: (product: ProductDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized product detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function ProductEditScreen({ productId, onSuccess, onCancel }: ProductEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: product,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(productDetailQueryKey(productId), () => fetchProduct(productId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('products.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !product) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <ProductForm mode={{ type: 'edit', product }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'products',
  basePath: '/products',
  defaultMode: OPEN_MODE_PAGE,
  generateRoutes: false,
  // The form renders its own identity bar (title, subtitle and the
  // save/cancel actions on one row), so the Sheet keeps its `SheetHeader`
  // `sr-only` instead of showing a second heading above it.
  formOwnsHeader: true,
  labelKey: 'navigation.products',
  DetailScreen: ProductDetailScreen,
  FormScreen: ProductFormScreen,
}