import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProduct, productDetailQueryKey } from '@/features/products/api'
import { ProductDetailView } from '@/features/products/product-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated read-only page of a single product (spec 0022, replaces the view
 * Sheet). Mirrors `RegistryDetailPage`: fresh re-authorized fetch on mount,
 * unchanged presentational `ProductDetailView`; the record card owns the
 * single "Edit" affordance (the record kit's `detailOwnsEditAction`
 * convention, Opportunità/Anagrafiche), gated by the `permissions` block of
 * THIS response.
 */
export default function ProductDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()
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

  if (productId === null) {
    return <NotFoundPage />
  }

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Button variant="outline" asChild>
            <Link to="/products">
              <ArrowLeft aria-hidden="true" />
              {t('common.back')}
            </Link>
          </Button>
        }
      />

      <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
        {isError ? (
          <DetailError
            message={t('products.detail.loadError')}
            retryLabel={t('common.retry')}
            onRetry={() => refetch()}
          />
        ) : isLoading || !product ? (
          <DetailLoading />
        ) : (
          <ProductDetailView
            product={product}
            onEdit={() => void navigate(`/products/${productId}/edit`)}
          />
        )}
      </div>
    </div>
  )
}
