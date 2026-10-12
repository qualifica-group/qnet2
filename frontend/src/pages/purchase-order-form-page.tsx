import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { PurchaseOrderFormScreen } from '@/features/purchase-orders/purchase-order-form-screen'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated page of an ODA: `/purchase-orders/new` creates, `/purchase-orders/:id`
 * opens it. Light composition only; the screen owns the loading and the form.
 */
export default function PurchaseOrderFormPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const isEdit = id !== undefined
  const orderId = parseEntityId(id)

  if (isEdit && orderId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission={isEdit ? 'purchase-orders.view' : 'purchase-orders.create'}
      fallback={<p className="text-sm text-muted-foreground">{t('purchaseOrders.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        <PurchaseOrderFormScreen id={orderId ?? undefined} />
      </div>
    </Can>
  )
}
