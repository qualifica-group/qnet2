import { useState } from 'react'
import { ChevronDown, Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { formatEuro } from '@/features/invoices/invoice-format'
import { idToSelectValue } from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import { PurchaseInvoiceEditorOrderLines } from '@/features/purchase-invoices/purchase-invoice-editor-order-lines'
import type { InvoiceableOrder } from '@/features/purchase-invoices/types'
import { useInvoiceableOrders } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'
import type { OrdersNotice } from '@/features/purchase-invoices/use-purchase-invoice-editor-orders'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

interface PurchaseInvoiceEditorOrdersSectionProps {
  supplierId: number
  companyId: number
  /** ODAs already linked to the document. */
  linkedOrderIds: readonly number[]
  isAdding: boolean
  notice: OrdersNotice
  onAdd: (orderId: number) => void
}

interface OrderEntryProps {
  order: InvoiceableOrder
  linked: boolean
  open: boolean
  isAdding: boolean
  onOpenChange: (open: boolean) => void
  onAdd: (orderId: number) => void
}

/** One invoiceable ODA: the toggle of its rows and the Add action. */
function OrderEntry({ order, linked, open, isAdding, onOpenChange, onAdd }: OrderEntryProps) {
  const { t } = useTranslation()
  const toggleKey = open ? 'hideLines' : 'showLines'
  return (
    <Collapsible open={open} onOpenChange={onOpenChange} asChild>
      <li className="rounded-md border bg-background text-xs">
        <div className="flex flex-wrap items-center justify-between gap-2 px-1 py-1">
          <CollapsibleTrigger
            aria-label={t(`purchaseInvoices.editor.orders.${toggleKey}`, { code: order.code })}
            className="flex min-w-0 flex-1 items-center gap-1 rounded-md px-1 py-0.5 text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
          >
            <ChevronDown
              className={cn('size-3.5 shrink-0 transition-transform', open ? '' : '-rotate-90')}
              aria-hidden="true"
            />
            <span className="min-w-0 truncate">
              <span className="font-medium">{order.code}</span> - {formatDate(order.order_date)} -{' '}
              {formatEuro(order.grand_total)}
            </span>
          </CollapsibleTrigger>
          {linked ? (
            <span className="px-1 text-muted-foreground">{t('purchaseInvoices.editor.orders.added')}</span>
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
        </div>
        <CollapsibleContent className="border-t">
          <PurchaseInvoiceEditorOrderLines orderId={order.id} code={order.code} />
        </CollapsibleContent>
      </li>
    </Collapsible>
  )
}

/**
 * Invoiceable ODAs of the chosen supplier and company: each one expands to its
 * billable rows; adding one appends its not-yet-invoiced rows and links it to
 * the document (create only).
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
  const [openOrderId, setOpenOrderId] = useState<number | null>(null)
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
          {data.map((order) => (
            <OrderEntry
              key={order.id}
              order={order}
              linked={linkedOrderIds.includes(order.id)}
              open={openOrderId === order.id}
              isAdding={isAdding}
              onOpenChange={(open) => setOpenOrderId(open ? order.id : null)}
              onAdd={onAdd}
            />
          ))}
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
