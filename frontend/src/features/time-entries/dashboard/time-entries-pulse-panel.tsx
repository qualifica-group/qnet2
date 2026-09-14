/**
 * "Polso operativo" card (spec 0122 D-11, AC-034): coverage + primary
 * cluster, mirroring q-net's `WorkActivitiesStatsPanels` pulse half
 * (structure/density, D-2) rebuilt on `components/ui/card`.
 */

import { useTranslation } from 'react-i18next'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { TimeEntriesClusterBadges } from '@/features/time-entries/dashboard/time-entries-cluster-badges'
import { TimeEntriesOtherClustersPopover } from '@/features/time-entries/dashboard/time-entries-other-clusters-popover'
import type { PulseStats } from '@/features/time-entries/types'

interface TimeEntriesPulsePanelProps {
  pulse?: PulseStats
  isLoading?: boolean
  className?: string
}

function PulseSkeleton() {
  return (
    <div className="grid gap-0 sm:grid-cols-2">
      {Array.from({ length: 2 }).map((_, index) => (
        <div className="border-b p-3 sm:border-b-0 sm:last:border-l" key={index}>
          <div className="flex min-h-28 flex-col items-center justify-center text-center">
            <Skeleton className="h-3 w-20" />
            <Skeleton className="mt-2 h-7 w-24" />
            <Skeleton className="mt-2 h-3 w-32" />
          </div>
        </div>
      ))}
    </div>
  )
}

export function TimeEntriesPulsePanel({ pulse, isLoading = false, className }: TimeEntriesPulsePanelProps) {
  const { t } = useTranslation()
  const primaryCluster = pulse?.primary_cluster ?? null
  const swatch = swatchClassFor(primaryCluster?.task_type.color)

  return (
    <Card className={cn('overflow-hidden py-0', className)}>
      <CardHeader className="gap-1 border-b py-3">
        <CardTitle className="text-base">{t('timeEntries.pulse.title')}</CardTitle>
        <CardDescription className="text-xs">{t('timeEntries.pulse.subtitle')}</CardDescription>
      </CardHeader>

      {isLoading || !pulse ? (
        <PulseSkeleton />
      ) : (
        <div className="grid gap-0 sm:grid-cols-2">
          <div className="border-b p-3 sm:border-r sm:border-b-0">
            <div className="flex min-h-28 min-w-0 flex-col items-center justify-center text-center">
              <div className="text-xs tracking-[0.12em] text-muted-foreground uppercase">
                {t('timeEntries.pulse.coverage')}
              </div>
              <div className="mt-3 flex min-w-0 flex-wrap items-end justify-center gap-2">
                <div className="text-xl font-semibold break-words sm:text-2xl">{pulse.coverage.percentage}%</div>
              </div>
              <div className="mt-2 text-sm text-muted-foreground">
                {pulse.coverage.tracked_days}/{pulse.coverage.working_days} {t('timeEntries.pulse.workingDays')}
              </div>
              <div className="mt-2 text-sm text-muted-foreground">{t('timeEntries.pulse.coverageCaption')}</div>
            </div>
          </div>

          <div className="p-3">
            <div className="flex min-h-28 min-w-0 flex-col items-center text-center">
              <div className="text-xs tracking-[0.12em] text-muted-foreground uppercase">
                {t('timeEntries.pulse.primaryClusterLabel')}
              </div>
              <div className="mt-3 flex min-w-0 flex-wrap items-center justify-center gap-2 text-xl font-semibold sm:text-2xl">
                <span
                  className={cn(
                    'inline-flex size-7 shrink-0 items-center justify-center rounded-full text-white',
                    swatch ?? 'bg-muted text-muted-foreground',
                  )}
                >
                  <DynamicIcon name={primaryCluster?.task_type.icon} className="size-4" />
                </span>
                <span className="break-words">
                  {primaryCluster?.task_type.name ?? t('timeEntries.pulse.noData')}
                </span>
              </div>
              {primaryCluster ? (
                <div className="mt-2">
                  <TimeEntriesClusterBadges percentage={primaryCluster.percentage} minutes={primaryCluster.minutes} />
                </div>
              ) : null}
              {pulse.other_clusters.length > 0 ? (
                <TimeEntriesOtherClustersPopover clusters={pulse.other_clusters} />
              ) : (
                <div className="mt-2 text-sm text-muted-foreground">{t('timeEntries.pulse.primaryCluster')}</div>
              )}
            </div>
          </div>
        </div>
      )}
    </Card>
  )
}
