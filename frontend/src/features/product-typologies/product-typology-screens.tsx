/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProductTypology } from '@/features/product-typologies/api'
import { ProductTypologyForm } from '@/features/product-typologies/product-typology-form'
import { ProductTypologyDetailView } from '@/features/product-typologies/product-typology-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { ProductTypologyDetail } from '@/features/product-typologies/types'

/** Query key for a single product typology's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['product-typologies', 'detail', id] as const
}

/**
 * Content-only `product-typologies` screens for the module registry (spec
 * 0042): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function ProductTypologyDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: productTypology,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchProductTypology(id))

  if (isError) {
    return (
      <DetailError
        message={t('productTypologies.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !productTypology) {
    return <DetailLoading />
  }

  return <ProductTypologyDetailView productTypology={productTypology} />
}

export function ProductTypologyFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: ProductTypologyDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <ProductTypologyForm
        mode={{ type: 'create' }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <ProductTypologyEditScreen
      productTypologyId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface ProductTypologyEditScreenProps {
  productTypologyId: number
  onSuccess: (productTypology: ProductTypologyDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized product typology detail before mounting
 * the edit form, so the partial PATCH starts from authoritative values
 * rather than a stale snapshot.
 */
function ProductTypologyEditScreen({
  productTypologyId,
  onSuccess,
  onCancel,
}: ProductTypologyEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: productTypology,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(productTypologyId), () => fetchProductTypology(productTypologyId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('productTypologies.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !productTypology) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <ProductTypologyForm
      mode={{ type: 'edit', productTypology }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'product-typologies',
  basePath: '/product-typologies',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.productTypologies',
  DetailScreen: ProductTypologyDetailScreen,
  FormScreen: ProductTypologyFormScreen,
}
