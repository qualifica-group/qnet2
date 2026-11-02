import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { AccountsChart } from '@/features/management-control/accounts-chart'
import { AccountsTable } from '@/features/management-control/accounts-table'
import { AccountsTotals } from '@/features/management-control/accounts-totals'
import type { AccountFlows, AccountFlowsFilters } from '@/features/management-control/accounts-types'
import { useAccountFlows } from '@/features/management-control/use-account-flows'

/** No receipt and no payment in the period: the selection has no data to show. */
function isEmptyAccountFlows(data: AccountFlows): boolean {
  return data.collections.installments === 0 && data.payments.installments === 0
}

/** Body of the Accounts page (spec 0271): loading, error with retry and empty states are handled here. */
export function AccountsPanel({ filters }: { filters: AccountFlowsFilters }) {
  const { t, i18n } = useTranslation()
  const { data, isPending, isError, error, refetch } = useAccountFlows(filters)

  return (
    <div className="flex min-w-0 flex-col gap-4">
      {isPending ? (
        <div role="status" aria-label={t('managementControl.accounts.loading')} className="flex flex-col gap-2">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      ) : null}
      {isError ? (
        <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
          <span>{apiErrorMessage(error, t('managementControl.accounts.error'), i18n)}</span>
          <Button type="button" size="sm" variant="secondary" onClick={() => void refetch()}>
            {t('managementControl.retry')}
          </Button>
        </div>
      ) : null}
      {data && isEmptyAccountFlows(data) ? (
        <p className="rounded-lg border bg-surface p-4 text-sm text-muted-foreground">{t('managementControl.accounts.empty')}</p>
      ) : null}
      {data && !isEmptyAccountFlows(data) ? (
        <>
          <AccountsTotals data={data} />
          <AccountsTable side="collections" data={data} />
          <AccountsTable side="payments" data={data} />
          <AccountsChart data={data} />
        </>
      ) : null}
    </div>
  )
}
