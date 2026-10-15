import { useTranslation } from 'react-i18next'
import { LineEditorTableRow, type LinesTableEditing } from '@/components/record-form/record-lines'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { InvoiceLine } from '@/features/invoices/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums'
const DATA_COLUMNS = 7

interface InvoiceLinesTableProps {
  lines: InvoiceLine[]
  /** The record's in-place editing (spec 0263); absent on the grid's detail panel. */
  editing?: LinesTableEditing
}

/** Compact table of the document lines; on a record, editable in place. */
export function InvoiceLinesTable({ lines, editing }: InvoiceLinesTableProps) {
  const { t } = useTranslation()
  const actions = editing?.actions
  const columns = actions ? DATA_COLUMNS + 1 : DATA_COLUMNS
  const openIndex = editing?.index ?? null

  if (lines.length === 0 && openIndex === null) {
    return <p className="px-2 py-3 text-xs text-muted-foreground">{t('invoices.detail.noLines')}</p>
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{t('invoices.detail.lines')}</caption>
        <thead className="border-b border-border bg-surface">
          <tr>
            <th scope="col" className={HEAD}>
              {t('invoices.detail.linesColumns.description')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('invoices.detail.linesColumns.quantity')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('invoices.detail.linesColumns.unit_price')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('invoices.detail.linesColumns.vat_rate')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('invoices.detail.linesColumns.net_amount')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('invoices.detail.linesColumns.vat_amount')}
            </th>
            <th scope="col" className={NUM_HEAD}>
              {t('invoices.detail.linesColumns.total_amount')}
            </th>
            {actions ? (
              <th scope="col" className={HEAD}>
                <span className="sr-only">{t('invoices.detail.linesColumns.actions')}</span>
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
                <td className={NUM_CELL}>{Number(line.quantity)}</td>
                <td className={NUM_CELL}>{formatEuro(line.unit_price)}</td>
                <td className={NUM_CELL}>{`${Number(line.vat_rate.rate)}%`}</td>
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
