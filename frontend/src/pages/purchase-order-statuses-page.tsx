import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PurchaseOrderStatusesTable } from '@/features/purchase-order-statuses/purchase-order-statuses-table'

/**
 * ODA statuses page (spec 0248). Light composition only: gates access with
 * `purchase-order-statuses.viewAny` and mounts the thin adapter, which mounts
 * the generic table (`domain="purchase-order-statuses"`).
 */
export function PurchaseOrderStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="purchase-order-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('purchaseOrderStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <PurchaseOrderStatusesTable />
      </div>
    </Can>
  )
}

export default PurchaseOrderStatusesPage
