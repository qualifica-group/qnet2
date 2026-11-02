import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { IncomeStatementFunctions } from '@/features/management-control/income-statement-functions'
import { IncomeStatementSegments } from '@/features/management-control/income-statement-segments'
import { IncomeStatementTotals } from '@/features/management-control/income-statement-totals'
import { StructureCostsPanel } from '@/features/management-control/structure-costs-panel'
import type { IncomeStatementFilters } from '@/features/management-control/types'
import { useIncomeStatement } from '@/features/management-control/use-income-statement'

/**
 * Body of the Income statement page (spec 0268): totals, statement by business function,
 * segments and structure costs for the filters chosen by the page. Loading, error with retry
 * and empty states are handled here.
 */
export function IncomeStatementPanel({ filters }: { filters: IncomeStatementFilters }) {
  const { t, i18n } = useTranslation()
  const { data, isPending, isError, error, refetch } = useIncomeStatement(filters)

  return (
    <div className="flex min-w-0 flex-col gap-4">
      {isPending ? (
        <div role="status" aria-label={t('managementControl.loading')} className="flex flex-col gap-2">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      ) : null}
      {isError ? (
        <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
          <span>{apiErrorMessage(error, t('managementControl.error'), i18n)}</span>
          <Button type="button" size="sm" variant="secondary" onClick={() => void refetch()}>
            {t('managementControl.retry')}
          </Button>
        </div>
      ) : null}
      {data && data.totals.work_orders_count === 0 ? (
        <p className="rounded-lg border bg-surface p-4 text-sm text-muted-foreground">{t('managementControl.empty')}</p>
      ) : null}
      {data && data.totals.work_orders_count > 0 ? (
        <>
          <IncomeStatementTotals totals={data.totals} />
          <IncomeStatementFunctions rows={data.by_business_function} total={data.totals} />
          <IncomeStatementSegments segments={data.segments} segmentBy={filters.segmentBy} />
        </>
      ) : null}
      <StructureCostsPanel filters={filters} />
    </div>
  )
}
