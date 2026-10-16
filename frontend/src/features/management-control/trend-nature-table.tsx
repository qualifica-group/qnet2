import { useTranslation } from 'react-i18next'
import {
  CELL,
  ExposureSection,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
} from '@/features/management-control/exposure-table'
import { formatShare } from '@/features/management-control/exposure-format'
import { roundMoney, sumMoney } from '@/features/management-control/money-math'
import { MoneyValue } from '@/features/management-control/money-value'
import { monthLabelFormatter } from '@/features/management-control/month-label'
import type { MonthlyTrend } from '@/features/management-control/trend-types'

const STICKY = 'sticky left-0 z-10 bg-card group-hover:bg-[color-mix(in_oklab,var(--card),var(--muted)_40%)]'

/** Share of the revenue as the server sends it (0..100, one decimal); "n/a" when there is no revenue to compare with. */
function ShareLabel({ share, locale }: { share: number | null; locale: string }) {
  const { t } = useTranslation()

  return (
    <span className="block text-xs text-muted-foreground tabular-nums">
      {share === null ? t('managementControl.trend.notAvailable') : formatShare(share, locale)}
    </span>
  )
}

/** Costs by month and nature, each with its share of the month revenue, and the revenue as the base (spec 0270 D-9). */
export function TrendNatureTable({ data }: { data: MonthlyTrend }) {
  const { t, i18n } = useTranslation()
  const monthLabel = monthLabelFormatter(i18n.language)
  const revenueTotal = sumMoney(data.totals.revenue)
  // `totals.cost` includes the internal labor, which has no nature: the rows add up to the purchase costs only.
  const purchaseCosts = data.totals.cost.map((value, index) => roundMoney(value - (data.totals.labor[index] ?? 0)))

  return (
    <ExposureSection title={t('managementControl.trend.nature.title')} hint={t('managementControl.trend.nature.hint')}>
      <ExposureTable caption={t('managementControl.trend.nature.caption')}>
        <thead className="border-b">
          <tr>
            <th scope="col" className={`${HEAD_CELL} ${STICKY}`}>{t('managementControl.trend.nature.nature')}</th>
            {data.months.map((month) => (
              <th key={month} scope="col" className={NUMERIC_HEAD_CELL}>{monthLabel(month)}</th>
            ))}
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.trend.nature.total')}</th>
          </tr>
        </thead>
        <tbody>
          {data.by_nature.map((row) => {
            const total = sumMoney(row.cost)
            return (
              <tr key={row.nature ?? 'none'} className="group border-t hover:bg-muted/40">
                <th scope="row" className={`${CELL} ${STICKY} text-left font-medium`}>
                  {t(`managementControl.trend.nature.natures.${row.nature ?? 'none'}`)}
                </th>
                {row.cost.map((value, index) => (
                  <td key={data.months[index]} className={NUMERIC_CELL}>
                    <MoneyValue value={value} />
                    <ShareLabel share={row.share_of_revenue[index] ?? null} locale={i18n.language} />
                  </td>
                ))}
                <td className={`${NUMERIC_CELL} font-semibold`}>
                  <MoneyValue value={total} />
                  <ShareLabel share={revenueTotal === 0 ? null : Math.round((total / revenueTotal) * 1000) / 10} locale={i18n.language} />
                </td>
              </tr>
            )
          })}
        </tbody>
        <tfoot className="border-t-2">
          <tr className="font-semibold">
            <th scope="row" className={`${CELL} ${STICKY} text-left`}>{t('managementControl.trend.nature.cost')}</th>
            {purchaseCosts.map((value, index) => (
              <td key={data.months[index]} className={NUMERIC_CELL}><MoneyValue value={value} /></td>
            ))}
            <td className={NUMERIC_CELL}><MoneyValue value={sumMoney(purchaseCosts)} /></td>
          </tr>
          <tr>
            <th scope="row" className={`${CELL} ${STICKY} text-left font-medium`}>{t('managementControl.trend.nature.revenue')}</th>
            {data.totals.revenue.map((value, index) => (
              <td key={data.months[index]} className={NUMERIC_CELL}><MoneyValue value={value} /></td>
            ))}
            <td className={NUMERIC_CELL}><MoneyValue value={revenueTotal} /></td>
          </tr>
        </tfoot>
      </ExposureTable>
    </ExposureSection>
  )
}
