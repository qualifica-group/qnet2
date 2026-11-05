import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { StatementRow } from '@/features/account-statements/types'

const CELL = 'px-2 py-1.5'
const NUMERIC = `${CELL} text-right tabular-nums whitespace-nowrap`

function formatDate(iso: string): string {
  const [year, month, day] = iso.split('-')
  return `${day}/${month}/${year}`
}

/** Chronological rows with running balance; horizontal scroll below the md breakpoint. */
export function StatementTable({ rows }: { rows: StatementRow[] }) {
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
      <table className="w-full min-w-[56rem] text-sm">
        <thead className="bg-surface text-xs text-muted-foreground">
          <tr className="text-left">
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
