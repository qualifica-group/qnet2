import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { BillingAgeTable } from '@/features/management-control/billing-age-table'
import { BillingBacklogTable } from '@/features/management-control/billing-backlog-table'
import { BillingBacklogTotals } from '@/features/management-control/billing-backlog-totals'
import { BillingProforma } from '@/features/management-control/billing-proforma'
import type { Billing, BillingFilters } from '@/features/management-control/billing-types'
import { useBilling } from '@/features/management-control/use-billing'

/** Nothing left to invoice and no proforma issued, converted or open: the selection has no data to show. */
function isEmptyBilling(data: Billing): boolean {
  const { backlog, proforma } = data
  return (
    backlog.work_orders_count === 0 &&
    proforma.issued.documents === 0 &&
    proforma.converted.documents === 0 &&
    proforma.open.documents === 0
  )
}

/** Body of the Billing page (spec 0271): loading, error with retry and empty states are handled here. */
export function BillingPanel({ filters }: { filters: BillingFilters }) {
  const { t, i18n } = useTranslation()
  const { data, isPending, isError, error, refetch } = useBilling(filters)

  return (
    <div className="flex min-w-0 flex-col gap-4">
      {isPending ? (
        <div role="status" aria-label={t('managementControl.billing.loading')} className="flex flex-col gap-2">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      ) : null}
      {isError ? (
        <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
          <span>{apiErrorMessage(error, t('managementControl.billing.error'), i18n)}</span>
          <Button type="button" size="sm" variant="secondary" onClick={() => void refetch()}>
            {t('managementControl.retry')}
          </Button>
        </div>
      ) : null}
      {data && isEmptyBilling(data) ? (
        <p className="rounded-lg border bg-surface p-4 text-sm text-muted-foreground">{t('managementControl.billing.empty')}</p>
      ) : null}
      {data && !isEmptyBilling(data) ? (
        <>
          <BillingBacklogTotals backlog={data.backlog} />
          <BillingBacklogTable data={data} />
          <BillingAgeTable backlog={data.backlog} />
          <BillingProforma proforma={data.proforma} />
        </>
      ) : null}
    </div>
  )
}
