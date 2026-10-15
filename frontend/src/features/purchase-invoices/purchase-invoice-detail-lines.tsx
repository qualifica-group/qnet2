import { useTranslation } from 'react-i18next'
import { LineEditorTableRow, type LinesTableEditing } from '@/components/record-form/record-lines'
import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { PurchaseInvoiceLine } from '@/features/purchase-invoices/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums'
const DATA_COLUMNS = 10

interface PurchaseInvoiceDetailLinesProps {
  lines: PurchaseInvoiceLine[]
  /** The record's in-place editing (spec 0263); absent on the grid's detail panel. */
  editing?: LinesTableEditing
}

/** Compact table of the document lines: cost type badge and work order per row; on a record, editable in place. */
export function PurchaseInvoiceDetailLines({ lines, editing }: PurchaseInvoiceDetailLinesProps) {
  const { t } = useTranslation()
  const title = t('purchaseInvoiceViews.detail.lines.title')
  const actions = editing?.actions
  const columns = actions ? DATA_COLUMNS + 1 : DATA_COLUMNS
  const openIndex = editing?.index ?? null

  if (lines.length === 0 && openIndex === null) {
    return <p className="px-2 py-3 text-xs text-muted-foreground">{t('purchaseInvoiceViews.detail.lines.empty')}</p>
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{title}</caption>
        <thead className="border-b border-border bg-surface">
          <tr>
            <th scope="col" className={HEAD}>
              {t('purchaseInvoiceViews.detail.lines.columns.description')}
            </th>
            <th scope="col" className={HEAD}>
              {t('purchaseInvoiceViews.detail.lines.columns.order')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('purchaseInvoiceViews.detail.lines.columns.quantity')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('purchaseInvoiceViews.detail.lines.columns.unit_price')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('purchaseInvoiceViews.detail.lines.columns.vat_rate')}
            </th>
            <th scope="col" className={HEAD}>
              {t('purchaseInvoiceViews.detail.lines.columns.cost_type')}
            </th>
            <th scope="col" className={HEAD}>
              {t('purchaseInvoiceViews.detail.lines.columns.work_order')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('purchaseInvoiceViews.detail.lines.columns.net_amount')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('purchaseInvoiceViews.detail.lines.columns.vat_amount')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('purchaseInvoiceViews.detail.lines.columns.total_amount')}
            </th>
            {actions ? (
              <th scope="col" className={HEAD}>
                <span className="sr-only">{t('purchaseInvoiceViews.detail.lines.columns.actions')}</span>
              </th>
            ) : null}
          </tr>
        </thead>
        <tbody>
          {lines.map((line, index) =>
            index === openIndex ? (
              <LineEditorTableRow key={line.id} colSpan={columns}>
                {editing?.row}
              </LineEditorTableRow>
            ) : (
              <tr key={line.id} className="border-b border-border last:border-b-0">
                <td className={`${CELL} max-w-80 truncate`} title={line.description}>
                  {line.description}
                </td>
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
                {actions ? <td className="px-1 py-0.5">{actions(index)}</td> : null}
              </tr>
            ),
          )}
          {openIndex !== null && openIndex >= lines.length ? (
            <LineEditorTableRow colSpan={columns}>{editing?.row}</LineEditorTableRow>
          ) : null}
        </tbody>
      </table>
    </div>
  )
}
