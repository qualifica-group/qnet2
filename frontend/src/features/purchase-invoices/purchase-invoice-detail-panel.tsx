import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { PurchaseInvoiceDetailInstallments } from '@/features/purchase-invoices/purchase-invoice-detail-installments'
import { PurchaseInvoiceDetailLines } from '@/features/purchase-invoices/purchase-invoice-detail-lines'
import { PurchaseInvoiceDetailOrders } from '@/features/purchase-invoices/purchase-invoice-detail-orders'
import { usePurchaseInvoice } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'
import type { TableRow } from '@/features/table/types'

/**
 * Master/detail row of the passive invoices grid: lazily loads the document and
 * shows its lines, installments and linked ODAs, like the active invoices panel. The real
 * height is pushed back to AG Grid through a ResizeObserver once loaded.
 */
export function PurchaseInvoiceDetailPanel({ data, node, api }: ICellRendererParams<TableRow>) {
  const { t } = useTranslation()
  const invoiceId = typeof data?.id === 'number' ? data.id : null
  const { data: invoice, isError, refetch } = usePurchaseInvoice(invoiceId)

  const contentRef = useRef<HTMLDivElement>(null)
  const isReady = invoice !== undefined
  useEffect(() => {
    const element = contentRef.current
    if (!element || !node || !api || typeof ResizeObserver === 'undefined') {
      return
    }
    const observer = new ResizeObserver(() => {
      node.setRowHeight(element.offsetHeight)
      api.onRowHeightChanged()
    })
    observer.observe(element)
    return () => observer.disconnect()
  }, [node, api, isReady])

  if (invoiceId === null) {
    return null
  }
  if (isError) {
    return (
      <div className="flex flex-col items-start gap-2 p-4">
        <p className="text-sm text-destructive">{t('purchaseInvoiceViews.detail.loadError')}</p>
        <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => void refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  if (!invoice) {
    return <Skeleton className="m-3 h-24" />
  }
  return (
    <div ref={contentRef} className="flex flex-col gap-3 p-3">
      <section className="flex flex-col gap-1.5">
        <h3 className="text-xs font-semibold text-foreground">{t('purchaseInvoiceViews.detail.lines.title')}</h3>
        <PurchaseInvoiceDetailLines lines={invoice.lines} />
      </section>
      <section className="flex flex-col gap-1.5">
        <h3 className="text-xs font-semibold text-foreground">{t('purchaseInvoiceViews.detail.installments.title')}</h3>
        <PurchaseInvoiceDetailInstallments installments={invoice.installments} />
      </section>
      <section className="flex flex-col gap-1.5">
        <h3 className="text-xs font-semibold text-foreground">{t('purchaseInvoiceViews.detail.orders.title')}</h3>
        <PurchaseInvoiceDetailOrders orders={invoice.purchase_orders} />
      </section>
    </div>
  )
}
