import { useTranslation } from 'react-i18next'
import { OdaLink } from '@/features/purchase-requests/oda-link'
import type { PurchaseOrderRef } from '@/features/purchase-requests/types'

interface LineStatusOrderNoticeProps {
  /** Orders that own the status of the selected lines (never empty). */
  orders: readonly PurchaseOrderRef[]
}

/** Shown in place of the status options when a selected line is driven by its ODA (spec 0245 D-8). */
export function LineStatusOrderNotice({ orders }: LineStatusOrderNoticeProps) {
  const { t } = useTranslation()

  return (
    <div role="status" className="flex flex-col gap-1.5 rounded-md border bg-surface px-3 py-2 text-xs">
      <p>{t('purchaseRequests.statusDialog.orderDriven')}</p>
      <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
        <span className="font-medium">{t('purchaseRequests.statusDialog.orderDrivenOpen')}</span>
        {orders.map((order) => (
          <OdaLink key={order.id} purchaseOrder={order} className="font-medium" />
        ))}
      </p>
    </div>
  )
}
