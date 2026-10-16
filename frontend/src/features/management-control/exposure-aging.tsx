import { useTranslation } from 'react-i18next'
import { ComparisonBarChart } from '@/components/ui/comparison-bar-chart'
import { formatEuro } from '@/features/invoices/invoice-format'
import {
  CELL,
  ExposureSection,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
} from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'
import { AGING_BUCKETS, type Exposure } from '@/features/management-control/exposure-types'

/** Open amounts by aging bucket, receivable against payable, as a table and a chart (spec 0269 D-4, D-16). */
export function ExposureAging({ data }: { data: Exposure }) {
  const { t } = useTranslation()
  const points = AGING_BUCKETS.map((bucket) => ({
    label: t(`managementControl.exposure.aging.buckets.${bucket}`),
    budget: data.receivable.aging[bucket],
    actual: data.payable.aging[bucket],
  }))

  return (
    <ExposureSection title={t('managementControl.exposure.aging.title')} hint={t('managementControl.exposure.aging.hint')}>
      <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
        <ExposureTable caption={t('managementControl.exposure.aging.caption')}>
          <thead className="bg-muted/40">
            <tr>
              <th scope="col" className={HEAD_CELL}>{t('managementControl.exposure.aging.bucket')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.aging.receivable')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.aging.payable')}</th>
            </tr>
          </thead>
          <tbody>
            {AGING_BUCKETS.map((bucket) => (
              <tr key={bucket} className="border-t hover:bg-muted/40">
                <th scope="row" className={`${CELL} text-left font-medium`}>
                  {t(`managementControl.exposure.aging.buckets.${bucket}`)}
                </th>
                <td className={NUMERIC_CELL}><MoneyValue value={data.receivable.aging[bucket]} /></td>
                <td className={NUMERIC_CELL}><MoneyValue value={data.payable.aging[bucket]} /></td>
              </tr>
            ))}
          </tbody>
          <tfoot className="border-t-2 font-semibold">
            <tr>
              <th scope="row" className={`${CELL} text-left`}>{t('managementControl.exposure.aging.total')}</th>
              <td className={NUMERIC_CELL}><MoneyValue value={data.receivable.open_total} /></td>
              <td className={NUMERIC_CELL}><MoneyValue value={data.payable.open_total} /></td>
            </tr>
          </tfoot>
        </ExposureTable>
        <ComparisonBarChart
          title={t('managementControl.exposure.aging.chartTitle')}
          points={points}
          seriesLabels={{
            budget: t('managementControl.exposure.aging.seriesReceivable'),
            actual: t('managementControl.exposure.aging.seriesPayable'),
          }}
          formatValue={formatEuro}
          headingLevel={4}
        />
      </div>
    </ExposureSection>
  )
}
