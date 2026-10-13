import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { useAbilities } from '@/features/auth/use-abilities'
import { PURCHASE_ORDERS_DOMAIN } from '@/features/purchase-orders/types'
import type { PurchaseOrderRef } from '@/features/purchase-invoices/types'

/** Linked ODAs as code chips; each links to the order when the actor may view purchase orders. */
export function PurchaseInvoiceDetailOrders({ orders }: { orders: PurchaseOrderRef[] }) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const canViewOrders = can(`${PURCHASE_ORDERS_DOMAIN}.view`)

  if (orders.length === 0) {
    return <p className="px-2 py-3 text-xs text-muted-foreground">{t('purchaseInvoiceViews.detail.orders.empty')}</p>
  }

  return (
    <ul className="flex flex-wrap gap-1.5" aria-label={t('purchaseInvoiceViews.detail.orders.title')}>
      {orders.map((order) => (
        <li key={order.id}>
          <Badge variant="secondary" className="font-mono">
            {canViewOrders ? (
              <Link to={`/${PURCHASE_ORDERS_DOMAIN}/${order.id}`} className="hover:underline">
                {order.code}
              </Link>
            ) : (
              order.code
            )}
          </Badge>
        </li>
      ))}
    </ul>
  )
}
