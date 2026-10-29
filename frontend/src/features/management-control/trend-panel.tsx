import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { TrendChart } from '@/features/management-control/trend-chart'
import { TrendFunctionTable } from '@/features/management-control/trend-function-table'
import { TrendNatureTable } from '@/features/management-control/trend-nature-table'
import { TrendTotals } from '@/features/management-control/trend-totals'
import type { MonthlyTrend, TrendFilters, TrendMeasure } from '@/features/management-control/trend-types'
import { useMonthlyTrend } from '@/features/management-control/use-monthly-trend'

/** No revenue and no cost in any month of the period: nothing to show (the previous year alone is not a trend). */
function isEmptyTrend(data: MonthlyTrend): boolean {
  return [...data.totals.revenue, ...data.totals.cost].every((value) => value === 0)
}

/**
 * Body of the Monthly trend page (spec 0270): the measure chosen for the function table also drives
 * the charts. Loading, error with retry and empty states are handled here.
 */
export function TrendPanel({ filters }: { filters: TrendFilters }) {
  const { t, i18n } = useTranslation()
  const { data, isLoading, isError, error, refetch } = useMonthlyTrend(filters)
  const [measure, setMeasure] = useState<TrendMeasure>('revenue')

  return (
    <div className="flex min-w-0 flex-col gap-4">
      {isLoading ? (
        <div role="status" aria-label={t('managementControl.trend.loading')} className="flex flex-col gap-2">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      ) : null}
      {isError ? (
        <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
          <span>{apiErrorMessage(error, t('managementControl.trend.error'), i18n)}</span>
          <Button type="button" size="sm" variant="secondary" onClick={() => void refetch()}>
            {t('managementControl.retry')}
          </Button>
        </div>
      ) : null}
      {data && isEmptyTrend(data) ? (
        <p className="rounded-lg border bg-surface p-4 text-sm text-muted-foreground">{t('managementControl.trend.empty')}</p>
      ) : null}
      {data && !isEmptyTrend(data) ? (
        <>
          <TrendTotals data={data} />
          <TrendFunctionTable data={data} measure={measure} onMeasureChange={setMeasure} />
          <TrendChart data={data} measure={measure} />
          <TrendNatureTable data={data} />
        </>
      ) : null}
    </div>
  )
}
