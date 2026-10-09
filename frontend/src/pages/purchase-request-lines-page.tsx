import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PurchaseRequestLinesTable } from '@/features/purchase-requests/purchase-request-lines-table'

/**
 * RDA line management page (Purchasing). Light composition only: gates access
 * with `purchase-requests.view` and mounts the thin adapter over the generic
 * table (`domain="purchase-request-lines"`).
 */
export default function PurchaseRequestLinesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="purchase-requests.view"
      fallback={<p className="text-sm text-muted-foreground">{t('purchaseRequests.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <PurchaseRequestLinesTable />
      </div>
    </Can>
  )
}
