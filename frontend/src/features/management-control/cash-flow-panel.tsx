import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { CashFlowByCompany } from '@/features/management-control/cash-flow-by-company'
import { CashFlowChart } from '@/features/management-control/cash-flow-chart'
import { applyBacklogScenario } from '@/features/management-control/cash-flow-scenario'
import { CashFlowTable } from '@/features/management-control/cash-flow-table'
import { CashFlowTotals } from '@/features/management-control/cash-flow-totals'
import type { CashFlow, CashFlowFilters, Flow } from '@/features/management-control/cash-flow-types'
import { useCashFlow } from '@/features/management-control/use-cash-flow'

function isEmptyFlow(flow: Flow): boolean {
  return flow.receivable === 0 && flow.payable === 0 && flow.committed === 0
}

/** Nothing open or committed in any column: the selection has no data to show. */
function isEmptyCashFlow(data: CashFlow): boolean {
  return [data.backlog, ...data.months, data.beyond].every(isEmptyFlow)
}

/**
 * Body of the Cash flow page (spec 0270): the server sends the base figures once, the backlog
 * scenario is a client-side view of them. Loading, error with retry and empty states are handled here.
 */
export function CashFlowPanel({ filters }: { filters: CashFlowFilters }) {
  const { t, i18n } = useTranslation()
  const { data, isPending, isError, error, refetch } = useCashFlow(filters)
  const [scenario, setScenario] = useState(false)

  return (
    <div className="flex min-w-0 flex-col gap-4">
      {isPending ? (
        <div role="status" aria-label={t('managementControl.cashFlow.loading')} className="flex flex-col gap-2">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      ) : null}
      {isError ? (
        <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
          <span>{apiErrorMessage(error, t('managementControl.cashFlow.error'), i18n)}</span>
          <Button type="button" size="sm" variant="secondary" onClick={() => void refetch()}>
            {t('managementControl.retry')}
          </Button>
        </div>
      ) : null}
      {data && isEmptyCashFlow(data) ? (
        <p className="rounded-lg border bg-surface p-4 text-sm text-muted-foreground">{t('managementControl.cashFlow.empty')}</p>
      ) : null}
      {data && !isEmptyCashFlow(data) ? (
        <CashFlowContent data={data} scenario={scenario} onScenarioChange={setScenario} />
      ) : null}
    </div>
  )
}

interface CashFlowContentProps {
  data: CashFlow
  scenario: boolean
  onScenarioChange: (scenario: boolean) => void
}

function CashFlowContent({ data, scenario, onScenarioChange }: CashFlowContentProps) {
  const { t } = useTranslation()
  const view = scenario ? applyBacklogScenario(data) : data

  return (
    <>
      <p className="text-xs text-muted-foreground">{t('managementControl.cashFlow.snapshot', { date: data.today })}</p>
      <CashFlowTotals data={data} />
      <CashFlowTable data={view} scenario={scenario} onScenarioChange={onScenarioChange} />
      <CashFlowChart data={view} />
      <CashFlowByCompany data={view} />
    </>
  )
}
