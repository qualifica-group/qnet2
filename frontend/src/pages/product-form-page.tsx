import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProduct, productDetailQueryKey } from '@/features/products/api'
import { ProductForm } from '@/features/products/product-form'
import type { ProductDetail } from '@/features/products/types'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated create/edit page of a product (spec 0022, replaces the create and
 * edit Sheets). One page serves `/products/new` and `/products/:id/edit`;
 * `ProductForm` and its hook/payload are reused as-is.
 */
export default function ProductFormPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const isEdit = id !== undefined
  const productId = parseEntityId(id)

  const {
    data: product,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(
    productDetailQueryKey(productId),
    () => fetchProduct(productId as number),
    productId !== null,
  )

  useBreadcrumbTitle(`/products/${id}`, product?.name)

  const onSuccess = useCallback(
    (saved: ProductDetail) => {
      queryClient.invalidateQueries({ queryKey: productDetailQueryKey(saved.id) })
      void navigate(`/products/${saved.id}`)
    },
    [navigate, queryClient],
  )

  const onCancel = useCallback(() => {
    void navigate(isEdit ? `/products/${productId}` : '/products')
  }, [isEdit, navigate, productId])

  if (isEdit && productId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission={isEdit ? 'products.update' : 'products.create'}
      fallback={<p className="text-sm text-muted-foreground">{t('products.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />

        {/* No heading of its own: the form owns the identity bar (title,
            subtitle and the save/cancel actions on one row), exactly as the
            `formOwnsHeader` modules do on the generic page. */}
        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
          {isError ? (
            <div className="flex flex-col items-start gap-3 p-4">
              <p className="text-sm text-destructive" role="alert">
                {t('products.detail.loadError')}
              </p>
              <Button variant="outline" size="sm" onClick={() => refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : isEdit && (isLoading || !product) ? (
            <div className="flex flex-col gap-4 p-4" aria-hidden="true">
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
            </div>
          ) : (
            <ProductForm
              mode={product ? { type: 'edit', product } : { type: 'create' }}
              onSuccess={onSuccess}
              onCancel={onCancel}
            />
          )}
        </div>
      </div>
    </Can>
  )
}
