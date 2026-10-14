import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { formatEuro } from '@/features/invoices/invoice-format'
import { idToSelectValue } from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import { useInvoiceableOrders } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'
import type { OrdersNotice } from '@/features/purchase-invoices/use-purchase-invoice-editor-orders'
import { formatDate } from '@/lib/formatting/date-display'

interface PurchaseInvoiceEditorOrdersSectionProps {
  supplierId: number
  companyId: number
  /** ODAs already linked to the document. */
  linkedOrderIds: readonly number[]
  isAdding: boolean
  notice: OrdersNotice
  onAdd: (orderId: number) => void
}

/**
 * Invoiceable ODAs of the chosen supplier and company: adding one appends its
 * not-yet-invoiced rows and links it to the document (create only).
 */
export function PurchaseInvoiceEditorOrdersSection({
  supplierId,
  companyId,
  linkedOrderIds,
  isAdding,
  notice,
  onAdd,
}: PurchaseInvoiceEditorOrdersSectionProps) {
  const { t } = useTranslation()
  const { data, isError, isPending } = useInvoiceableOrders(
    idToSelectValue(supplierId),
    idToSelectValue(companyId),
  )

  return (
    <section aria-labelledby="purchase-invoice-editor-orders" className="grid gap-2 rounded-lg border bg-card p-3">
      <h3 id="purchase-invoice-editor-orders" className="text-sm font-semibold">
        {t('purchaseInvoices.editor.sections.orders')}
      </h3>
      {isError ? (
        <p role="alert" className="text-xs text-destructive">
          {t('purchaseInvoices.editor.orders.loadError')}
        </p>
      ) : isPending ? (
        <p className="text-xs text-muted-foreground">{t('purchaseInvoices.editor.orders.loading')}</p>
      ) : data.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('purchaseInvoices.editor.orders.empty')}</p>
      ) : (
        <ul className="grid gap-1">
          {data.map((order) => {
            const linked = linkedOrderIds.includes(order.id)
            return (
              <li key={order.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-background px-2 py-1 text-xs">
                <span className="min-w-0 truncate">
                  <span className="font-medium">{order.code}</span> - {formatDate(order.order_date)} - {formatEuro(order.grand_total)}
                </span>
                {linked ? (
                  <span className="text-muted-foreground">{t('purchaseInvoices.editor.orders.added')}</span>
                ) : (
                  <Button
                    type="button"
                    size="xs"
                    variant="secondary"
                    disabled={isAdding}
                    aria-label={t('purchaseInvoices.editor.orders.addOrder', { code: order.code })}
                    onClick={() => onAdd(order.id)}
                  >
                    <Plus />
                    {t('purchaseInvoices.editor.orders.add')}
                  </Button>
                )}
              </li>
            )
          })}
        </ul>
      )}
      {notice ? (
        <p role="status" className="text-xs text-muted-foreground">
          {t(`purchaseInvoices.editor.orders.notice.${notice}`)}
        </p>
      ) : null}
    </section>
  )
}
