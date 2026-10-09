import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { PurchaseRequestFormScreen } from '@/features/purchase-requests/purchase-request-form-screen'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated page of an RDA: `/purchase-requests/new` creates, `/purchase-requests/:id`
 * opens it for editing (read-only when closed or not updatable). Light
 * composition only; the screen owns the loading and the form.
 */
export default function PurchaseRequestFormPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const isEdit = id !== undefined
  const requestId = parseEntityId(id)

  if (isEdit && requestId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission={isEdit ? 'purchase-requests.view' : 'purchase-requests.create'}
      fallback={<p className="text-sm text-muted-foreground">{t('purchaseRequests.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        <PurchaseRequestFormScreen id={requestId ?? undefined} />
      </div>
    </Can>
  )
}
