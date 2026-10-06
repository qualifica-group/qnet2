import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { InvoiceLine } from '@/features/invoices/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums'

/** Compact read-only table of the document lines. */
export function InvoiceLinesTable({ lines }: { lines: InvoiceLine[] }) {
  const { t } = useTranslation()

  if (lines.length === 0) {
    return <p className="px-2 py-3 text-xs text-muted-foreground">{t('invoices.detail.noLines')}</p>
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{t('invoices.detail.lines')}</caption>
        <thead className="border-b border-border bg-surface">
          <tr>
            <th scope="col" className={HEAD}>{t('invoices.detail.linesColumns.description')}</th>
            <th scope="col" className={NUM_HEAD}>{t('invoices.detail.linesColumns.quantity')}</th>
            <th scope="col" className={NUM_HEAD}>{t('invoices.detail.linesColumns.unit_price')}</th>
            <th scope="col" className={NUM_HEAD}>{t('invoices.detail.linesColumns.vat_rate')}</th>
            <th scope="col" className={NUM_HEAD}>{t('invoices.detail.linesColumns.net_amount')}</th>
            <th scope="col" className={NUM_HEAD}>{t('invoices.detail.linesColumns.vat_amount')}</th>
            <th scope="col" className={NUM_HEAD}>{t('invoices.detail.linesColumns.total_amount')}</th>
          </tr>
        </thead>
        <tbody>
          {lines.map((line) => (
            <tr key={line.id} className="border-b border-border last:border-b-0">
              <td className={`${CELL} max-w-80 truncate`} title={line.description}>{line.description}</td>
              <td className={NUM_CELL}>{Number(line.quantity)}</td>
              <td className={NUM_CELL}>{formatEuro(line.unit_price)}</td>
              <td className={NUM_CELL}>{`${Number(line.vat_rate.rate)}%`}</td>
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
