import { useTranslation } from 'react-i18next'
import { SegmentedControl, type SegmentedControlOption } from '@/components/ui/segmented-control'
import {
  CELL,
  ExposureSection,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
} from '@/features/management-control/exposure-table'
import { formatVariation, sumMoney, variationPercent } from '@/features/management-control/money-math'
import { MoneyValue } from '@/features/management-control/money-value'
import { monthLabelFormatter } from '@/features/management-control/month-label'
import { trendRowLabel } from '@/features/management-control/trend-row-label'
import { TREND_MEASURES, type MonthlyTrend, type TrendMeasure } from '@/features/management-control/trend-types'

const STICKY = 'sticky left-0 z-10 bg-card group-hover:bg-muted'

interface TrendFunctionTableProps {
  data: MonthlyTrend
  measure: TrendMeasure
  onMeasureChange: (measure: TrendMeasure) => void
}

/** Business functions by month on the chosen measure, closed by the totals, the previous year and the change % (spec 0270 D-7, D-8). */
export function TrendFunctionTable({ data, measure, onMeasureChange }: TrendFunctionTableProps) {
  const { t, i18n } = useTranslation()
  const monthLabel = monthLabelFormatter(i18n.language)
  const measureLabel = t(`managementControl.trend.measures.${measure}`)
  const options: SegmentedControlOption<TrendMeasure>[] = TREND_MEASURES.map((value) => ({
    value,
    label: t(`managementControl.trend.measures.${value}`),
  }))
  const current = data.totals[measure]
  const previous = data.totals.previous_year[measure]
  const currentTotal = sumMoney(current)
  const previousTotal = sumMoney(previous)
  /** One change per month, then the one of the whole period. */
  const variations = [
    ...current.map((value, index) => variationPercent(value, previous[index] ?? 0)),
    variationPercent(currentTotal, previousTotal),
  ]

  return (
    <ExposureSection title={t('managementControl.trend.functions.title')} hint={t('managementControl.trend.functions.hint')}>
      <SegmentedControl
        aria-label={t('managementControl.trend.functions.measure')}
        className="self-start"
        value={measure}
        options={options}
        onValueChange={onMeasureChange}
      />
      <ExposureTable caption={t('managementControl.trend.functions.caption', { measure: measureLabel })}>
        <thead className="border-b">
          <tr>
            <th scope="col" className={`${HEAD_CELL} ${STICKY}`}>{t('managementControl.trend.functions.businessFunction')}</th>
            {data.months.map((month) => (
              <th key={month} scope="col" className={NUMERIC_HEAD_CELL}>{monthLabel(month)}</th>
            ))}
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.trend.functions.total')}</th>
          </tr>
        </thead>
        <tbody>
          {data.functions.map((row) => (
            <tr key={row.key} className="group border-t hover:bg-muted/40">
              <th scope="row" title={trendRowLabel(t, row)} className={`${CELL} ${STICKY} max-w-48 truncate text-left font-medium`}>
                {trendRowLabel(t, row)}
              </th>
              {row[measure].map((value, index) => (
                <td key={data.months[index]} className={NUMERIC_CELL}><MoneyValue value={value} /></td>
              ))}
              <td className={`${NUMERIC_CELL} font-semibold`}><MoneyValue value={sumMoney(row[measure])} /></td>
            </tr>
          ))}
        </tbody>
        <tfoot className="border-t-2">
          <tr className="font-semibold">
            <th scope="row" className={`${CELL} ${STICKY} text-left`}>{t('managementControl.trend.functions.total')}</th>
            {current.map((value, index) => (
              <td key={data.months[index]} className={NUMERIC_CELL}><MoneyValue value={value} /></td>
            ))}
            <td className={NUMERIC_CELL}><MoneyValue value={currentTotal} /></td>
          </tr>
          <tr>
            <th scope="row" className={`${CELL} ${STICKY} text-left font-medium`}>{t('managementControl.trend.functions.previousYear')}</th>
            {previous.map((value, index) => (
              <td key={data.months[index]} className={NUMERIC_CELL}><MoneyValue value={value} /></td>
            ))}
            <td className={NUMERIC_CELL}><MoneyValue value={previousTotal} /></td>
          </tr>
          <tr>
            <th scope="row" className={`${CELL} ${STICKY} text-left font-medium`}>{t('managementControl.trend.functions.variation')}</th>
            {variations.map((variation, index) => (
              <td key={data.months[index] ?? 'total'} className={`${NUMERIC_CELL} tabular-nums`}>
                {variation === null ? t('managementControl.trend.notAvailable') : formatVariation(variation, i18n.language)}
              </td>
            ))}
          </tr>
        </tfoot>
      </ExposureTable>
    </ExposureSection>
  )
}
