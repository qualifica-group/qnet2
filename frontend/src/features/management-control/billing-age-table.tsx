import { useTranslation } from 'react-i18next'
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

/** What is left to invoice by age of the work order since its start (spec 0271 D-3). */
export function BillingAgeTable({ backlog }: { backlog: Billing['backlog'] }) {
  const { t } = useTranslation()

  return (
    <ExposureSection title={t('managementControl.billing.age.title')} hint={t('managementControl.billing.age.hint')}>
      <ExposureTable caption={t('managementControl.billing.age.caption')}>
        <thead className="bg-muted/40">
          <tr>
            <th scope="col" className={HEAD_CELL}>{t('managementControl.billing.age.bucket')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.billing.backlog.workOrders')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.billing.totals.to_invoice')}</th>
          </tr>
        </thead>
        <tbody>
          {backlog.by_age.map((row) => (
            <tr key={row.bucket} className="border-t hover:bg-muted/40">
              <th scope="row" className={`${CELL} text-left font-medium`}>{t(`managementControl.billing.age.buckets.${row.bucket}`)}</th>
              <td className={`${NUMERIC_CELL} tabular-nums`}>{row.work_orders_count}</td>
              <td className={NUMERIC_CELL}><MoneyValue value={row.to_invoice} /></td>
            </tr>
          ))}
        </tbody>
        <tfoot className="border-t-2 font-semibold">
          <tr>
            <th scope="row" className={`${CELL} text-left`}>{t('managementControl.billing.backlog.total')}</th>
            <td className={`${NUMERIC_CELL} tabular-nums`}>{backlog.work_orders_count}</td>
            <td className={NUMERIC_CELL}><MoneyValue value={backlog.to_invoice} /></td>
          </tr>
        </tfoot>
      </ExposureTable>
    </ExposureSection>
  )
}
