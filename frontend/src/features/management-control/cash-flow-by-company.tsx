import { useTranslation } from 'react-i18next'
import type { CashFlow } from '@/features/management-control/cash-flow-types'
import {
  CELL,
  ExposureSection,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
} from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'
import { sumMoney } from '@/features/management-control/money-math'
import { monthLabelFormatter } from '@/features/management-control/month-label'
import { rowLabel } from '@/features/management-control/row-label'

const STICKY = 'sticky left-0 z-10 bg-card group-hover:bg-muted'

/** Net cash flow by company: backlog, months, beyond and total, with the closing line of the whole selection (spec 0270 D-5). */
export function CashFlowByCompany({ data }: { data: CashFlow }) {
  const { t, i18n } = useTranslation()
  const monthLabel = monthLabelFormatter(i18n.language)
  const totalNet = sumMoney([data.backlog.net, ...data.months.map((month) => month.net), data.beyond.net])

  return (
    <ExposureSection title={t('managementControl.cashFlow.byCompany.title')} hint={t('managementControl.cashFlow.byCompany.hint')}>
      <ExposureTable caption={t('managementControl.cashFlow.byCompany.caption')}>
        <thead className="border-b">
          <tr>
            <th scope="col" className={`${HEAD_CELL} ${STICKY}`}>{t('managementControl.cashFlow.byCompany.company')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.cashFlow.table.backlog')}</th>
            {data.months.map((month) => (
              <th key={month.month} scope="col" className={NUMERIC_HEAD_CELL}>{monthLabel(month.month)}</th>
            ))}
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.cashFlow.table.beyond')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.cashFlow.byCompany.total')}</th>
          </tr>
        </thead>
        <tbody>
          {data.by_company.map((row) => (
            <tr key={row.key} className="group border-t hover:bg-muted/40">
              <th scope="row" title={rowLabel(t, row)} className={`${CELL} ${STICKY} max-w-48 truncate text-left font-medium`}>
                {rowLabel(t, row)}
              </th>
              <td className={NUMERIC_CELL}><MoneyValue value={row.backlog_net} /></td>
              {row.months.map((month) => (
                <td key={month.month} className={NUMERIC_CELL}><MoneyValue value={month.net} /></td>
              ))}
              <td className={NUMERIC_CELL}><MoneyValue value={row.beyond_net} /></td>
              <td className={`${NUMERIC_CELL} font-semibold`}><MoneyValue value={row.total_net} /></td>
            </tr>
          ))}
        </tbody>
        <tfoot className="border-t-2 font-semibold">
          <tr>
            <th scope="row" className={`${CELL} ${STICKY} text-left`}>{t('managementControl.cashFlow.byCompany.total')}</th>
            <td className={NUMERIC_CELL}><MoneyValue value={data.backlog.net} /></td>
            {data.months.map((month) => (
              <td key={month.month} className={NUMERIC_CELL}><MoneyValue value={month.net} /></td>
            ))}
            <td className={NUMERIC_CELL}><MoneyValue value={data.beyond.net} /></td>
            <td className={NUMERIC_CELL}><MoneyValue value={totalNet} /></td>
          </tr>
        </tfoot>
      </ExposureTable>
    </ExposureSection>
  )
}
