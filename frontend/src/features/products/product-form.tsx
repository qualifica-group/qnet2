import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { fetchProductNextCode } from '@/features/products/api'
import { ProductFormBody } from '@/features/products/product-form-body'
import type { ProductDetail } from '@/features/products/types'

interface ProductFormProps {
  /** Called after a successful create so the caller can close + refresh. */
  onSuccess: (product: ProductDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The product create form (spec 0218: there is no edit form, the detail edits
 * in place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/products`) and the next sequential code
 * (spec 0065) before rendering `ProductFormBody`.
 */
export function ProductForm(props: ProductFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('products')
  // Kept uncached (staleTime/gcTime 0) so each new form gets a fresh
  // suggestion; an error degrades gracefully to an empty code to type.
  const nextCode = useQuery({
    queryKey: ['products', 'next-code'],
    queryFn: fetchProductNextCode,
    staleTime: 0,
    gcTime: 0,
  })

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

  if (!metaQuery.data || nextCode.isLoading) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <ProductFormBody {...props} initialCode={nextCode.data ?? ''} />
    </ResourcePermissionsProvider>
  )
}
