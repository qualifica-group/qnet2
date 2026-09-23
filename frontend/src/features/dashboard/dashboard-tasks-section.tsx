import { useTranslation } from 'react-i18next'
import { AlertCircle, ListChecks } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DashboardSectionHeader } from '@/features/dashboard/dashboard-section-header'
import { DashboardTaskCard, DashboardTaskCardSkeleton } from '@/features/dashboard/dashboard-task-card'
import {
  DASHBOARD_TASK_CARD_HREFS,
  DASHBOARD_TASK_CARD_ICONS,
  DASHBOARD_TASK_CARD_ORDER,
  DASHBOARD_TASK_CARD_TONES,
  DASHBOARD_TASK_TO_VALIDATE_HREF,
} from '@/features/dashboard/dashboard-task-card-config'
import { useDashboardTaskCounters } from '@/features/dashboard/use-dashboard-task-counters'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'

const TASK_CARDS_GRID_CLASS = 'grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5'

/**
 * "Attività da completare" section (spec 0151 AC-009): the 5 counter cards, then
 * the Task module KPI tiles in the SAME block, without the charts (user
 * directives 2026-09-23: activities to complete and Task are one thing on the
 * dashboard; by-status, by-priority and monthly charts not wanted there).
 */
export function DashboardTasksSection() {
  const { t } = useTranslation()
  const { data, isLoading, isError, refetch } = useDashboardTaskCounters()

  return (
    <section aria-labelledby="dashboard-tasks-heading" className="flex flex-col gap-3">
      <DashboardSectionHeader
        id="dashboard-tasks-heading"
        icon={<ListChecks aria-hidden="true" />}
        title={t('dashboard.tasksSection.title')}
      />

      {isLoading ? (
        <div className={TASK_CARDS_GRID_CLASS}>
          {DASHBOARD_TASK_CARD_ORDER.map((key) => (
            <DashboardTaskCardSkeleton key={key} />
          ))}
        </div>
      ) : isError ? (
        <div
          role="alert"
          className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-l-2 border-destructive/40 border-l-destructive bg-destructive/10 px-4 py-3 text-sm"
        >
          <span className="flex items-center gap-2 font-medium text-foreground">
            <AlertCircle className="size-4 shrink-0 text-destructive" aria-hidden="true" />
            {t('dashboard.tasksSection.loadError')}
          </span>
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => void refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : data ? (
        <div className={TASK_CARDS_GRID_CLASS}>
          {DASHBOARD_TASK_CARD_ORDER.map((key) => {
            const Icon = DASHBOARD_TASK_CARD_ICONS[key]
            const bucket = data[key]
            const toValidate =
              key === 'assigned_by_me' && data.assigned_by_me.to_validate.count > 0
                ? { count: data.assigned_by_me.to_validate.count, href: DASHBOARD_TASK_TO_VALIDATE_HREF }
                : undefined

            return (
              <DashboardTaskCard
                key={key}
                title={t(`dashboard.tasksSection.cards.${key}.label`)}
                description={t(`dashboard.tasksSection.cards.${key}.description`)}
                value={bucket.count}
                totalMinutes={bucket.total_minutes}
                href={DASHBOARD_TASK_CARD_HREFS[key]}
                icon={<Icon aria-hidden="true" />}
                tone={DASHBOARD_TASK_CARD_TONES[key]}
                toValidate={toValidate}
              />
            )
          })}
        </div>
      ) : null}

      <ModuleStatsPanel domain="tasks" isOpen showCharts={false} />
    </section>
  )
}
