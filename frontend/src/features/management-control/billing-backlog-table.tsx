import { useTranslation } from 'react-i18next'
import { ComparisonBarChart } from '@/components/ui/comparison-bar-chart'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { Billing } from '@/features/management-control/billing-types'
import {
  CELL,
  ExposureSection,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
} from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'
import { rowLabel } from '@/features/management-control/row-label'

const STICKY = 'sticky left-0 z-10 bg-card group-hover:bg-[color-mix(in_oklab,var(--card),var(--muted)_40%)]'
const FIGURES = ['contract', 'invoiced', 'to_invoice'] as const

/** What is left to invoice by the chosen dimension, closed by the totals, with contract against invoiced (spec 0271 D-2, D-10). */
export function BillingBacklogTable({ data }: { data: Billing }) {
  const { t } = useTranslation()
  const { backlog } = data
  const dimension = t(`managementControl.billing.filters.groupBys.${data.applied.group_by}`)
  const totals = { contract: backlog.contract, invoiced: backlog.invoiced, to_invoice: backlog.to_invoice }

  return (
    <ExposureSection title={t('managementControl.billing.backlog.title', { dimension })} hint={t('managementControl.billing.backlog.hint', { date: data.today })}>
      <ExposureTable caption={t('managementControl.billing.backlog.caption', { dimension })}>
        <thead>
          <tr>
            <th scope="col" className={`${HEAD_CELL} ${STICKY}`}>{dimension}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.billing.backlog.workOrders')}</th>
            {FIGURES.map((figure) => (
              <th key={figure} scope="col" className={NUMERIC_HEAD_CELL}>{t(`managementControl.billing.totals.${figure}`)}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {backlog.by_dimension.map((row) => (
            <tr key={row.key} className="group border-t hover:bg-muted/40">
              <th scope="row" title={rowLabel(t, row)} className={`${CELL} ${STICKY} max-w-48 truncate text-left font-medium`}>
                {rowLabel(t, row)}
              </th>
              <td className={`${NUMERIC_CELL} tabular-nums`}>{row.work_orders_count}</td>
              {FIGURES.map((figure) => (
                <td key={figure} className={NUMERIC_CELL}><MoneyValue value={row[figure]} /></td>
              ))}
            </tr>
          ))}
        </tbody>
        <tfoot className="border-t-2 font-semibold">
          <tr>
            <th scope="row" className={`${CELL} ${STICKY} text-left`}>{t('managementControl.billing.backlog.total')}</th>
            <td className={`${NUMERIC_CELL} tabular-nums`}>{backlog.work_orders_count}</td>
            {FIGURES.map((figure) => (
              <td key={figure} className={NUMERIC_CELL}><MoneyValue value={totals[figure]} /></td>
            ))}
          </tr>
        </tfoot>
      </ExposureTable>
      <ComparisonBarChart
        title={t('managementControl.billing.backlog.chartTitle', { dimension })}
        points={backlog.by_dimension.map((row) => ({ label: rowLabel(t, row), budget: row.contract, actual: row.invoiced }))}
        seriesLabels={{
          budget: t('managementControl.billing.totals.contract'),
          actual: t('managementControl.billing.totals.invoiced'),
        }}
        formatValue={formatEuro}
        emptyLabel={t('managementControl.billing.backlog.chartEmpty')}
        headingLevel={4}
      />
    </ExposureSection>
  )
}
