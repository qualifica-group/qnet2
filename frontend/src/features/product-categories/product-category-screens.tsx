/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProductCategory } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { ProductCategoryDetail } from '@/features/product-categories/types'

/**
 * Content-only `product-categories` screens for the module registry (spec
 * 0042): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `ProductCategoriesTable`'s inline loaders, which the rewire removed. Create
 * always starts with no pre-selected parent (`parentId: null`): the tree's
 * "add subcategory" affordance that pre-selects one is out of scope of this
 * generic entry point.
 */
export function ProductCategoryDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: category,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(productCategoryKeys.detail(id), () => fetchProductCategory(id))

  if (isError) {
    return (
      <DetailError
        message={t('productCategories.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !category) {
    return <DetailLoading />
  }

  return <ProductCategoryDetailView category={category} />
}

export function ProductCategoryFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: ProductCategoryDetail) => {
    queryClient.setQueryData(productCategoryKeys.detail(saved.id), saved)
    void queryClient.invalidateQueries({ queryKey: productCategoryKeys.tree })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <ProductCategoryForm
        mode={{ type: 'create', parentId: null }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <ProductCategoryEditScreen categoryId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface ProductCategoryEditScreenProps {
  categoryId: number
  onSuccess: (category: ProductCategoryDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized category detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function ProductCategoryEditScreen({
  categoryId,
  onSuccess,
  onCancel,
}: ProductCategoryEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: category,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(productCategoryKeys.detail(categoryId), () => fetchProductCategory(categoryId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('productCategories.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !category) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <ProductCategoryForm mode={{ type: 'edit', category }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'product-categories',
  basePath: '/product-categories',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.productCategories',
  // The form renders its own identity bar (title, subtitle and the
  // save/cancel actions on one row), so the dedicated page drops its heading
  // and the Sheet keeps its `SheetHeader` `sr-only`.
  formOwnsHeader: true,
  DetailScreen: ProductCategoryDetailScreen,
  FormScreen: ProductCategoryFormScreen,
}