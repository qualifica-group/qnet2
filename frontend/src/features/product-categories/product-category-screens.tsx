/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProductCategory } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
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
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`). Create always starts
 * with no pre-selected parent (`parentId: null`): the tree's "add subcategory"
 * affordance that pre-selects one is out of scope of this generic entry point.
 *
 * Spec 0229: the detail edits its rows in place, so `onEdit` is never used;
 * each save reports through `onChanged`.
 */
export function ProductCategoryDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: category,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(productCategoryKeys.detail(id), () => fetchProductCategory(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('productCategories.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !category) {
    return <DetailLoading />
  }

  return <ProductCategoryDetailView category={category} onChanged={onChanged} />
}

/**
 * Create and duplicate only (spec 0229): there is no edit form, and the
 * registry generates no `:id/edit` route. Leaving a category being created —
 * Cancel, the Sheet's X/overlay/Esc, a link, a reload — always asks first.
 */
export function ProductCategoryFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('productCategories.form.leaveConfirm.title'),
    description: t('productCategories.form.leaveConfirm.description'),
    confirmLabel: t('productCategories.form.leaveConfirm.confirm'),
    cancelLabel: t('productCategories.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type === 'edit') {
    return null
  }

  const handleSuccess = (saved: ProductCategoryDetail) => {
    queryClient.invalidateQueries({ queryKey: productCategoryKeys.detail(saved.id) })
    // Saved: the navigation to the new category's detail is no "leaving".
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
      {mode.type === 'duplicate' ? (
        <ProductCategoryDuplicateLoader categoryId={mode.id} onSuccess={handleSuccess} onCancel={handleCancel} />
      ) : (
        <ProductCategoryForm
          mode={{ type: 'create', parentId: null }}
          onSuccess={handleSuccess}
          onCancel={handleCancel}
        />
      )}
    </>
  )
}

interface ProductCategoryDuplicateLoaderProps {
  categoryId: number
  onSuccess: (category: ProductCategoryDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized source category before mounting the create
 * form pre-filled from it (row action "duplicate"), so the copy starts from
 * authoritative values rather than a stale grid snapshot. The form itself
 * still submits via the create path (`ProductCategoryFormMode: 'duplicate'`).
 */
function ProductCategoryDuplicateLoader({ categoryId, onSuccess, onCancel }: ProductCategoryDuplicateLoaderProps) {
  const { t } = useTranslation()
  const {
    data: category,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(productCategoryKeys.detail(categoryId), () => fetchProductCategory(categoryId))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('productCategories.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !category) {
    return <RecordFormSkeleton />
  }

  return <ProductCategoryForm mode={{ type: 'duplicate', source: category }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'product-categories',
  basePath: '/product-categories',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.productCategories',
  // The detail IS the edit form (spec 0229): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
  DetailScreen: ProductCategoryDetailScreen,
  FormScreen: ProductCategoryFormScreen,
}
