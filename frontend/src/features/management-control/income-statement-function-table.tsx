import { useTranslation } from 'react-i18next'
import { HoursValue } from '@/features/management-control/income-statement-hours'
import { MoneyValue } from '@/features/management-control/money-value'
import { rowLabel } from '@/features/management-control/row-label'
import type { FunctionRow, IncomeStatementTotals } from '@/features/management-control/types'

const HEAD_CELL = 'px-2 py-1.5 text-left text-xs font-medium text-muted-foreground'
const NUMERIC_HEAD_CELL = 'px-2 py-1.5 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1.5 text-sm'
const STICKY = 'sticky left-0 z-10 bg-card group-hover:bg-[color-mix(in_oklab,var(--card),var(--muted)_40%)]'
const NUMERIC_CELL = 'px-2 py-1.5 text-right text-sm'

const FIGURE_COLUMNS = [
  'revenue_contract',
  'revenue_actual',
  'cost_budget',
  'cost_manual',
  'cost_committed',
  'cost_invoiced',
  'cost_labor',
] as const

const RESULT_COLUMNS = ['cost_actual', 'commission_actual', 'margin_budget', 'margin_actual', 'margin_delta'] as const

interface FunctionTableProps {
  rows: FunctionRow[]
  /** Totals line closing the table: the whole selection, or the subtotal of one segment. */
  total: IncomeStatementTotals
  caption: string
}

/** Income statement by business function, with a closing total line; reused by the main block and every segment. */
export function IncomeStatementFunctionTable({ rows, total, caption }: FunctionTableProps) {
  const { t } = useTranslation()

  return (
    <div
      tabIndex={0}
      role="region"
      aria-label={caption}
      className="min-w-0 overflow-auto rounded-lg border bg-card focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
    >
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{caption}</caption>
        <thead className="border-b">
          <tr>
            <th scope="col" className={`${HEAD_CELL} ${STICKY}`}>
              {t('managementControl.functions.businessFunction')}
            </th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.functions.workOrders')}</th>
            {FIGURE_COLUMNS.map((column) => (
              <th key={column} scope="col" className={NUMERIC_HEAD_CELL}>
                {t(`managementControl.figures.${column}`)}
              </th>
            ))}
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.functions.hours')}</th>
            {RESULT_COLUMNS.map((column) => (
              <th key={column} scope="col" className={NUMERIC_HEAD_CELL}>
                {t(`managementControl.figures.${column}`)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.key} className="group border-t hover:bg-muted/40">
              <th
                scope="row"
                title={rowLabel(t, row)}
                className={`${CELL} ${STICKY} max-w-48 truncate text-left font-medium`}
              >
                {rowLabel(t, row)}
              </th>
              <td className={`${NUMERIC_CELL} tabular-nums`}>{row.work_orders_count}</td>
              {FIGURE_COLUMNS.map((column) => (
                <td key={column} className={NUMERIC_CELL}>
                  <MoneyValue value={row[column]} />
                </td>
              ))}
              <td className={NUMERIC_CELL}>
                <HoursValue laborMinutes={row.labor_minutes} unpricedMinutes={row.unpriced_minutes} />
              </td>
              {RESULT_COLUMNS.map((column) => (
                <td key={column} className={NUMERIC_CELL}>
                  <MoneyValue value={row[column]} />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
        <tfoot className="border-t-2 font-semibold">
          <tr>
            <th scope="row" className={`${CELL} ${STICKY} text-left`}>
              {t('managementControl.functions.total')}
            </th>
            <td className={`${NUMERIC_CELL} tabular-nums`}>{total.work_orders_count}</td>
            {FIGURE_COLUMNS.map((column) => (
              <td key={column} className={NUMERIC_CELL}>
                <MoneyValue value={total[column]} />
              </td>
            ))}
            <td className={NUMERIC_CELL}>
              <HoursValue laborMinutes={total.labor_minutes} unpricedMinutes={total.unpriced_minutes} />
            </td>
            {RESULT_COLUMNS.map((column) => (
              <td key={column} className={NUMERIC_CELL}>
                <MoneyValue value={total[column]} />
              </td>
            ))}
          </tr>
        </tfoot>
      </table>
    </div>
  )
}
