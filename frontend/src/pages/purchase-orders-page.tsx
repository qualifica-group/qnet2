import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PurchaseOrdersTable } from '@/features/purchase-orders/purchase-orders-table'

/**
 * ODA list page (Purchasing). Light composition only: gates access with
 * `purchase-orders.view` and mounts the thin adapter over the generic table
 * (`domain="purchase-orders"`).
 */
export default function PurchaseOrdersPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="purchase-orders.view"
      fallback={<p className="text-sm text-muted-foreground">{t('purchaseOrders.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <PurchaseOrdersTable />
      </div>
    </Can>
  )
}
