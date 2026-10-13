import { useTranslation } from 'react-i18next'
import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { PurchaseInvoiceLine } from '@/features/purchase-invoices/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums'

/** Compact read-only table of the document lines: cost type badge and work order per row. */
export function PurchaseInvoiceDetailLines({ lines }: { lines: PurchaseInvoiceLine[] }) {
  const { t } = useTranslation()
  const title = t('purchaseInvoiceViews.detail.lines.title')

  if (lines.length === 0) {
    return <p className="px-2 py-3 text-xs text-muted-foreground">{t('purchaseInvoiceViews.detail.lines.empty')}</p>
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{title}</caption>
        <thead className="border-b border-border bg-surface">
          <tr>
            <th scope="col" className={HEAD}>{t('purchaseInvoiceViews.detail.lines.columns.description')}</th>
            <th scope="col" className={HEAD}>{t('purchaseInvoiceViews.detail.lines.columns.order')}</th>
            <th scope="col" className={NUM_HEAD}>{t('purchaseInvoiceViews.detail.lines.columns.quantity')}</th>
            <th scope="col" className={NUM_HEAD}>{t('purchaseInvoiceViews.detail.lines.columns.unit_price')}</th>
            <th scope="col" className={NUM_HEAD}>{t('purchaseInvoiceViews.detail.lines.columns.vat_rate')}</th>
            <th scope="col" className={HEAD}>{t('purchaseInvoiceViews.detail.lines.columns.cost_type')}</th>
            <th scope="col" className={HEAD}>{t('purchaseInvoiceViews.detail.lines.columns.work_order')}</th>
            <th scope="col" className={NUM_HEAD}>{t('purchaseInvoiceViews.detail.lines.columns.net_amount')}</th>
            <th scope="col" className={NUM_HEAD}>{t('purchaseInvoiceViews.detail.lines.columns.vat_amount')}</th>
            <th scope="col" className={NUM_HEAD}>{t('purchaseInvoiceViews.detail.lines.columns.total_amount')}</th>
          </tr>
        </thead>
        <tbody>
          {lines.map((line) => (
            <tr key={line.id} className="border-b border-border last:border-b-0">
              <td className={`${CELL} max-w-80 truncate`} title={line.description}>{line.description}</td>
              <td className={CELL}>{line.purchase_order?.code ?? ''}</td>
              <td className={NUM_CELL}>{Number(line.quantity)}</td>
              <td className={NUM_CELL}>{formatEuro(line.unit_price)}</td>
              <td className={NUM_CELL}>{line.vat_rate ? `${Number(line.vat_rate.rate)}%` : ''}</td>
              <td className={CELL}>
                <CostTypeBadge name={line.cost_type.name} color={line.cost_type.color} />
              </td>
              <td className={CELL}>{line.work_order?.code ?? ''}</td>
              <td className={NUM_CELL}>{formatEuro(line.net_amount)}</td>
              <td className={NUM_CELL}>{formatEuro(line.vat_amount)}</td>
              <td className={`${NUM_CELL} font-medium`}>{formatEuro(line.total_amount)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
