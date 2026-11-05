import { useTranslation } from 'react-i18next'
import { Check } from 'lucide-react'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { StatementRow } from '@/features/account-statements/types'

const CELL = 'px-2 py-1.5'
const NUMERIC = `${CELL} text-right tabular-nums whitespace-nowrap`

function formatDate(iso: string): string {
  const [year, month, day] = iso.split('-')
  return `${day}/${month}/${year}`
}

export interface StatementSelection {
  selected: ReadonlySet<string>
  onToggle: (key: string) => void
  onToggleAll: (checked: boolean) => void
}

interface StatementTableProps {
  rows: StatementRow[]
  /** Present only for users who may tick rows (`treasury-movements.reconcile`): adds the checkbox column. */
  selection?: StatementSelection
}

/** Chronological rows with running balance; horizontal scroll below the md breakpoint. */
export function StatementTable({ rows, selection }: StatementTableProps) {
  const { t } = useTranslation()
  if (rows.length === 0) {
    return (
      <p className="rounded-lg border bg-surface p-4 text-sm text-muted-foreground">
        {t('accountStatements.empty')}
      </p>
    )
  }

  // The app has no routable detail page for invoices (they open in sheets from their grids),
  // so the document is plain text, not a link.
  const documentLabel = (row: StatementRow): string =>
    row.document
      ? `${t(`accountStatements.documentKinds.${row.document.kind}`)} ${row.document.number ?? row.document.id}`
      : ''

  return (
    <div className="overflow-x-auto rounded-lg border bg-card">
      <table className="w-full min-w-[60rem] text-sm">
        <thead className="bg-surface text-xs text-muted-foreground">
          <tr className="text-left">
            {selection ? (
              <th scope="col" className={`${CELL} w-8`}>
                <input
                  type="checkbox"
                  className="size-3.5 accent-primary"
                  aria-label={t('accountStatements.reconciliation.selectAll')}
                  checked={rows.every((row) => selection.selected.has(row.key))}
                  onChange={(event) => selection.onToggleAll(event.target.checked)}
                />
              </th>
            ) : null}
            <th scope="col" className={`${CELL} w-10`}>{t('accountStatements.columns.reconciled')}</th>
            <th scope="col" className={CELL}>{t('accountStatements.columns.date')}</th>
            <th scope="col" className={CELL}>{t('accountStatements.columns.type')}</th>
            <th scope="col" className={CELL}>{t('accountStatements.columns.description')}</th>
            <th scope="col" className={CELL}>{t('accountStatements.columns.registry')}</th>
            <th scope="col" className={CELL}>{t('accountStatements.columns.document')}</th>
            <th scope="col" className={`${CELL} text-right`}>{t('accountStatements.columns.inflow')}</th>
            <th scope="col" className={`${CELL} text-right`}>{t('accountStatements.columns.outflow')}</th>
            <th scope="col" className={`${CELL} text-right`}>{t('accountStatements.columns.balance')}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.key} className="border-t hover:bg-muted/40">
              {selection ? (
                <td className={CELL}>
                  <input
                    type="checkbox"
                    className="size-3.5 accent-primary"
                    aria-label={t('accountStatements.reconciliation.selectRow', { description: row.description })}
                    checked={selection.selected.has(row.key)}
                    onChange={() => selection.onToggle(row.key)}
                  />
                </td>
              ) : null}
              <td className={CELL}>
                {row.reconciled ? (
                  <>
                    <Check className="size-3.5 text-primary" aria-hidden="true" />
                    <span className="sr-only">{t('accountStatements.reconciliation.reconciledRow')}</span>
                  </>
                ) : null}
              </td>
              <td className={`${CELL} whitespace-nowrap`}>{formatDate(row.date)}</td>
              <td className={`${CELL} whitespace-nowrap`}>
                {t(`accountStatements.types.${row.type}`, { defaultValue: row.type })}
              </td>
              <td className={`${CELL} max-w-xs truncate`} title={row.description}>{row.description}</td>
              <td className={`${CELL} max-w-48 truncate`}>{row.registry?.name ?? ''}</td>
              <td className={`${CELL} whitespace-nowrap`}>{documentLabel(row)}</td>
              <td className={NUMERIC}>{formatEuro(row.inflow)}</td>
              <td className={NUMERIC}>{formatEuro(row.outflow)}</td>
              <td className={`${NUMERIC} font-medium`}>{formatEuro(row.balance)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
