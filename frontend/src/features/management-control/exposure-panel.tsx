import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ExposureAging } from '@/features/management-control/exposure-aging'
import { ExposureByDimension } from '@/features/management-control/exposure-by-dimension'
import { ExposureCommitted } from '@/features/management-control/exposure-committed'
import { ExposureConcentration } from '@/features/management-control/exposure-concentration'
import { ExposureStatusTable } from '@/features/management-control/exposure-status-table'
import { ExposureTopTable } from '@/features/management-control/exposure-top-table'
import { ExposureTotals } from '@/features/management-control/exposure-totals'
import type { Exposure, ExposureFilters } from '@/features/management-control/exposure-types'
import { useExposure } from '@/features/management-control/use-exposure'

/** Nothing open, committed, invoiced or settled: the selection has no data to show. */
function isEmptyExposure(data: Exposure): boolean {
  const { receivable, payable, committed, concentration } = data
  return (
    receivable.open_total === 0 &&
    payable.open_total === 0 &&
    committed.total === 0 &&
    receivable.invoiced_in_period === 0 &&
    payable.invoiced_in_period === 0 &&
    receivable.settled_in_period === 0 &&
    payable.settled_in_period === 0 &&
    concentration.rows.length === 0
  )
}

/**
 * Body of the Schedule and exposure page (spec 0269): snapshot at today for open amounts, flows
 * of the period for the rest. Loading, error with retry and empty states are handled here.
 */
export function ExposurePanel({ filters }: { filters: ExposureFilters }) {
  const { t } = useTranslation()
  const { data, isPending, isError, refetch } = useExposure(filters)

  return (
    <div className="flex min-w-0 flex-col gap-4">
      {isPending ? (
        <div role="status" aria-label={t('managementControl.exposure.loading')} className="flex flex-col gap-2">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      ) : null}
      {isError ? (
        <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
          <span>{t('managementControl.exposure.error')}</span>
          <Button type="button" size="sm" variant="secondary" onClick={() => void refetch()}>
            {t('managementControl.retry')}
          </Button>
        </div>
      ) : null}
      {data && isEmptyExposure(data) ? (
        <p className="rounded-lg border bg-surface p-4 text-sm text-muted-foreground">{t('managementControl.exposure.empty')}</p>
      ) : null}
      {data && !isEmptyExposure(data) ? (
        <>
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-xs text-muted-foreground">{t('managementControl.exposure.snapshot', { date: data.today })}</p>
            <div className="flex flex-wrap gap-2">
              <Button asChild size="sm" variant="secondary">
                <Link to="/invoice-installments">{t('managementControl.exposure.links.receivable')}</Link>
              </Button>
              <Button asChild size="sm" variant="secondary">
                <Link to="/purchase-invoice-installments">{t('managementControl.exposure.links.payable')}</Link>
              </Button>
            </div>
          </div>
          <ExposureTotals data={data} />
          <ExposureAging data={data} />
          <ExposureStatusTable data={data} />
          <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
            <ExposureTopTable side="receivable" rows={data.receivable.top} />
            <ExposureTopTable side="payable" rows={data.payable.top} />
          </div>
          <ExposureCommitted data={data} />
          <ExposureByDimension data={data} groupBy={filters.groupBy} />
          <ExposureConcentration data={data.concentration} />
        </>
      ) : null}
    </div>
  )
}
