import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import type { CashFlow, Flow } from '@/features/management-control/cash-flow-types'
import {
  CELL,
  ExposureSection,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
} from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'
import { monthLabelFormatter } from '@/features/management-control/month-label'

const STICKY = 'sticky left-0 z-10 bg-card group-hover:bg-muted'
const ROWS = ['receivable', 'payable', 'committed', 'net', 'cumulative'] as const
type RowKey = (typeof ROWS)[number]
/** The closing figures of the table: bold, the first one with a heavier separator. */
const TOTAL_ROWS: readonly RowKey[] = ['net', 'cumulative']

interface Column {
  key: string
  header: string
  flow: Flow
  /** Running net: only the months of the horizon have one (the backlog and the rest are outside it). */
  cumulative: number | null
}

interface CashFlowTableProps {
  /** Data as shown: already transformed when the scenario is on. */
  data: CashFlow
  scenario: boolean
  onScenarioChange: (scenario: boolean) => void
}

/** Receipts, payments, commitments, net and cumulative by column: backlog, months of the horizon, beyond (spec 0270 D-3). */
export function CashFlowTable({ data, scenario, onScenarioChange }: CashFlowTableProps) {
  const { t, i18n } = useTranslation()
  const switchId = useId()
  const monthLabel = monthLabelFormatter(i18n.language)

  const columns: Column[] = [
    { key: 'backlog', header: t('managementControl.cashFlow.table.backlog'), flow: data.backlog, cumulative: null },
    ...data.months.map((month) => ({
      key: month.month,
      header: monthLabel(month.month),
      flow: month,
      cumulative: month.cumulative,
    })),
    { key: 'beyond', header: t('managementControl.cashFlow.table.beyond'), flow: data.beyond, cumulative: null },
  ]

  return (
    <ExposureSection title={t('managementControl.cashFlow.table.title')} hint={t('managementControl.cashFlow.table.hint')}>
      <div className="flex items-center gap-2">
        <Switch id={switchId} checked={scenario} onCheckedChange={onScenarioChange} />
        <Label htmlFor={switchId} className="text-xs">{t('managementControl.cashFlow.table.scenario')}</Label>
      </div>
      <ExposureTable caption={t('managementControl.cashFlow.table.caption')}>
        <thead className="border-b">
          <tr>
            <th scope="col" className={`${HEAD_CELL} ${STICKY}`}>{t('managementControl.cashFlow.table.item')}</th>
            {columns.map((column) => (
              <th key={column.key} scope="col" className={NUMERIC_HEAD_CELL}>{column.header}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {ROWS.map((row) => (
            <tr
              key={row}
              className={`group hover:bg-muted/40 ${row === 'net' ? 'border-t-2' : 'border-t'} ${TOTAL_ROWS.includes(row) ? 'font-semibold' : ''}`}
            >
              <th scope="row" className={`${CELL} ${STICKY} text-left font-medium`}>
                {t(`managementControl.cashFlow.table.rows.${row}`)}
              </th>
              {columns.map((column) => (
                <td key={column.key} className={NUMERIC_CELL}>
                  <ColumnValue column={column} row={row} />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </ExposureTable>
    </ExposureSection>
  )
}

function ColumnValue({ column, row }: { column: Column; row: RowKey }) {
  const value = row === 'cumulative' ? column.cumulative : column.flow[row]

  return value === null ? <span aria-hidden="true" className="text-muted-foreground">-</span> : <MoneyValue value={value} />
}
