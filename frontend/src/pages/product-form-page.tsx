import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { productDetailQueryKey } from '@/features/products/api'
import { GuardedProductForm } from '@/features/products/guarded-product-form'
import type { ProductDetail } from '@/features/products/types'

/**
 * Dedicated create page of a product (`/products/new`, spec 0022). There is no
 * edit page (spec 0218): the detail edits in place. The form sits behind its
 * leave guard, and a successful create lands on the new detail.
 */
export default function ProductFormPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const onSuccess = (saved: ProductDetail) => {
    queryClient.invalidateQueries({ queryKey: productDetailQueryKey(saved.id) })
    void navigate(`/products/${saved.id}`)
  }

  return (
    <Can
      permission="products.create"
      fallback={<p className="text-sm text-muted-foreground">{t('products.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        {/* The same chrome as the detail page: the leave guard intercepts the link. */}
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

        {/* No `bg-card`: the record canvas paints its own `bg-surface` and the
            cards inside it are the `bg-card` rung (ui-design.md §1-bis). */}
        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border">
          <GuardedProductForm onSuccess={onSuccess} onCancel={() => void navigate('/products')} />
        </div>
      </div>
    </Can>
  )
}
