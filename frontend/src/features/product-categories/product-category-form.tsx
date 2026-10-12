import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { ProductCategoryFormBody } from '@/features/product-categories/product-category-form-body'
import type {
  ProductCategoryDetail,
  ProductCategoryFormMode,
} from '@/features/product-categories/types'

interface ProductCategoryFormProps {
  mode: Exclude<ProductCategoryFormMode, { type: 'edit' }>
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (category: ProductCategoryDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The product category create form (spec 0229: there is no edit form, the
 * detail edits in place). Metadata-driven (spec 0004): resolves the
 * create-context `ResourcePermissions` (`GET /meta/product-categories`) before
 * rendering `ProductCategoryFormBody`; a duplicate is a create too.
 */
export function ProductCategoryForm(props: ProductCategoryFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('product-categories')

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (!metaQuery.data) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <ProductCategoryFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
