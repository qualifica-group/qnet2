import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import { formatQuantity } from '@/features/purchase-orders/purchase-order-receipt-schema'
import { usePurchaseInvoiceDraft } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'

interface PurchaseInvoiceEditorOrderLinesProps {
  orderId: number
  code: string
}

const NUMBER_CELL = 'px-2 py-1 text-right tabular-nums'

/**
 * Read-only rows of one invoiceable ODA, shown under its entry in the picker
 * before adding it: the order's draft, so already-invoiced rows are left out.
 */
export function PurchaseInvoiceEditorOrderLines({ orderId, code }: PurchaseInvoiceEditorOrderLinesProps) {
  const { t } = useTranslation()
  const { data, isError, isPending } = usePurchaseInvoiceDraft([orderId])

  if (isError) {
    return (
      <p role="alert" className="px-2 py-1 text-xs text-destructive">
        {t('purchaseInvoices.editor.orders.lines.loadError')}
      </p>
    )
  }
  if (isPending) {
    return <p className="px-2 py-1 text-xs text-muted-foreground">{t('purchaseInvoices.editor.orders.lines.loading')}</p>
  }
  if (data.lines.length === 0) {
    return <p className="px-2 py-1 text-xs text-muted-foreground">{t('purchaseInvoices.editor.orders.lines.empty')}</p>
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-xs">
        <caption className="sr-only">{t('purchaseInvoices.editor.orders.lines.caption', { code })}</caption>
        <thead>
          <tr className="text-muted-foreground">
            <th scope="col" className="px-2 py-1 text-left font-medium">
              {t('purchaseInvoices.editor.orders.lines.description')}
            </th>
            <th scope="col" className={`${NUMBER_CELL} font-medium`}>
              {t('purchaseInvoices.editor.orders.lines.ordered')}
            </th>
            <th scope="col" className={`${NUMBER_CELL} font-medium`}>
              {t('purchaseInvoices.editor.orders.lines.available')}
            </th>
            <th scope="col" className={`${NUMBER_CELL} font-medium`}>
              {t('purchaseInvoices.editor.orders.lines.unitPrice')}
            </th>
          </tr>
        </thead>
        <tbody>
          {data.lines.map((line) => (
            <tr key={line.purchase_order_line_id ?? line.sort_order} className="border-t">
              <td className="max-w-[16rem] truncate px-2 py-1">{line.description}</td>
              <td className={NUMBER_CELL}>
                {line.ordered_quantity === undefined ? '' : formatQuantity(line.ordered_quantity)}
              </td>
              <td className={NUMBER_CELL}>{formatQuantity(line.available_quantity ?? line.quantity)}</td>
              <td className={NUMBER_CELL}>{formatEuro(line.unit_price)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
