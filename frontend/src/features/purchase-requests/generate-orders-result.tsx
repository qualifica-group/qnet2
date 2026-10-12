import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { CheckCircle2 } from 'lucide-react'
import type { GenerateOrdersResult } from '@/features/purchase-requests/types'

const PURCHASE_ORDERS_PATH = '/purchase-orders'

/** Outcome of "Generate ODA": one link per created order, plus how many RDA closed by themselves. */
export function GenerateOrdersResultView({ result }: { result: GenerateOrdersResult }) {
  const { t } = useTranslation()
  const closed = result.closed_purchase_request_ids.length
  return (
    <div className="flex flex-col gap-3">
      <p role="status" className="flex items-center gap-1.5 text-sm font-medium">
        <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-300" aria-hidden="true" />
        {t('purchaseRequests.generateOrders.created', { count: result.purchase_orders.length })}
      </p>
      <ul aria-label={t('purchaseRequests.generateOrders.createdList')} className="rounded-lg border border-border bg-card text-xs">
        {result.purchase_orders.map((order) => (
          <li key={order.id} className="flex items-center justify-between gap-3 border-b border-border px-2 py-1.5 last:border-b-0">
            <Link to={`${PURCHASE_ORDERS_PATH}/${order.id}`} className="font-medium text-primary underline-offset-2 hover:underline">
              {order.code}
            </Link>
            <span className="truncate text-muted-foreground">
              {order.supplier.name} - {t('purchaseRequests.generateOrders.linesCount', { count: order.lines_count })}
            </span>
          </li>
        ))}
      </ul>
      {closed > 0 ? (
        <p className="text-xs text-muted-foreground">{t('purchaseRequests.generateOrders.autoClosed', { count: closed })}</p>
      ) : null}
    </div>
  )
}
