import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { AgentsChart } from '@/features/management-control/agents-chart'
import { AgentsTable } from '@/features/management-control/agents-table'
import { AgentsTotals } from '@/features/management-control/agents-totals'
import type { Agents, AgentsFilters } from '@/features/management-control/agents-types'
import { useAgents } from '@/features/management-control/use-agents'

/** No agent with accruals, settlements or payments in the selection: nothing to show. */
function isEmptyAgents(data: Agents): boolean {
  return data.agents.length === 0 && data.others === null
}

/** Body of the Agents page (spec 0272): loading, error with retry and empty states are handled here. */
export function AgentsPanel({ filters }: { filters: AgentsFilters }) {
  const { t } = useTranslation()
  const { data, isLoading, isError, refetch } = useAgents(filters)

  return (
    <div className="flex min-w-0 flex-col gap-4">
      {isLoading ? (
        <div role="status" aria-label={t('managementControl.agents.loading')} className="flex flex-col gap-2">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      ) : null}
      {isError ? (
        <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
          <span>{t('managementControl.agents.error')}</span>
          <Button type="button" size="sm" variant="secondary" onClick={() => void refetch()}>
            {t('managementControl.retry')}
          </Button>
        </div>
      ) : null}
      {data && isEmptyAgents(data) ? (
        <p className="rounded-lg border bg-surface p-4 text-sm text-muted-foreground">{t('managementControl.agents.empty')}</p>
      ) : null}
      {data && !isEmptyAgents(data) ? (
        <>
          <AgentsTotals totals={data.totals} />
          <AgentsTable data={data} />
          <AgentsChart data={data} />
        </>
      ) : null}
    </div>
  )
}
