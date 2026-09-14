/**
 * "Altri cluster (N)" popover of the pulse panel (spec 0122 D-11, AC-034):
 * lists every `Cluster` beyond the primary one. Mirrors q-net's
 * `WorkActivitiesOtherClustersPopover` 1:1 (structure/density), rebuilt on
 * `components/ui/popover` and the curated icon catalogue instead of Tabler.
 */

import { useTranslation } from 'react-i18next'
import { ChevronDown } from 'lucide-react'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { TimeEntriesClusterBadges } from '@/features/time-entries/dashboard/time-entries-cluster-badges'
import type { Cluster } from '@/features/time-entries/types'

interface TimeEntriesOtherClustersPopoverProps {
  clusters: Cluster[]
}

export function TimeEntriesOtherClustersPopover({ clusters }: TimeEntriesOtherClustersPopoverProps) {
  const { t } = useTranslation()

  if (clusters.length === 0) {
    return null
  }

  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          className="mt-3 inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-2 py-1 text-[10px] tracking-[0.12em] text-muted-foreground uppercase transition-colors hover:bg-muted hover:text-foreground data-[state=open]:bg-muted data-[state=open]:text-foreground"
        >
          <span>
            {t('timeEntries.pulse.otherClusters')} ({clusters.length})
          </span>
          <ChevronDown
            className="size-3.5 shrink-0 transition-transform data-[state=open]:rotate-180"
            aria-hidden="true"
          />
        </button>
      </PopoverTrigger>
      <PopoverContent align="center" className="w-72 p-2">
        <div className="flex w-full min-w-0 flex-col gap-1.5">
          {clusters.map((cluster) => (
            <div
              key={cluster.task_type.id}
              className="flex min-w-0 items-center justify-between gap-2 rounded-md border border-border bg-background px-2 py-1 text-xs"
            >
              <div className="flex min-w-0 items-center gap-1.5">
                <span
                  className={
                    swatchClassFor(cluster.task_type.color)
                      ? `inline-flex size-5 shrink-0 items-center justify-center rounded-full text-white ${swatchClassFor(cluster.task_type.color)}`
                      : 'inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground'
                  }
                >
                  <DynamicIcon name={cluster.task_type.icon} className="size-3" />
                </span>
                <span className="truncate font-medium text-foreground">{cluster.task_type.name}</span>
              </div>
              <TimeEntriesClusterBadges percentage={cluster.percentage} minutes={cluster.minutes} size="sm" />
            </div>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  )
}
