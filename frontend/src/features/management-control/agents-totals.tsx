import { useTranslation } from 'react-i18next'
import type { AgentFigures, AgentTotals } from '@/features/management-control/agents-types'
import { MoneyValue } from '@/features/management-control/money-value'

const MONEY_TILES: readonly (keyof AgentFigures)[] = ['accrued', 'to_settle', 'in_statement', 'approved_unpaid', 'paid']

/** Six KPI tiles (spec 0272): the five commission figures and the agents involved; 2 and 3 columns leave no orphan. */
export function AgentsTotals({ totals }: { totals: AgentTotals }) {
  const { t } = useTranslation()

  return (
    <section aria-label={t('managementControl.agents.totals.title')}>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {MONEY_TILES.map((field) => (
          <div key={field} className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
            <dt className="line-clamp-2 text-xs text-muted-foreground">{t(`managementControl.agents.figures.${field}`)}</dt>
            <dd className={field === 'to_settle' ? 'text-base font-semibold' : 'text-sm font-medium'}>
              <MoneyValue value={totals[field]} />
            </dd>
            {field === 'in_statement' ? (
              <dd className="text-xs text-muted-foreground">
                {t('managementControl.agents.totals.pendingDocument', { count: totals.pending_document_count })}
              </dd>
            ) : null}
          </div>
        ))}
        <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
          <dt className="line-clamp-2 text-xs text-muted-foreground">{t('managementControl.agents.totals.agents_count')}</dt>
          <dd className="text-sm font-medium tabular-nums">{totals.agents_count}</dd>
        </div>
      </dl>
    </section>
  )
}
