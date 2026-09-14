/**
 * Overview + Polso operativo grid (spec 0122 D-11, AC-034): the tiles panel
 * takes the wider column, the pulse panel the narrower one, matching q-net's
 * `lg:grid-cols-[minmax(0,1.65fr)_minmax(320px,0.95fr)]` (D-2).
 */

import { cn } from '@/lib/utils'
import { TimeEntriesOverviewTiles } from '@/features/time-entries/dashboard/time-entries-overview-tiles'
import { TimeEntriesPulsePanel } from '@/features/time-entries/dashboard/time-entries-pulse-panel'
import { useTimeEntriesStats } from '@/features/time-entries/dashboard/use-time-entries-stats'
import type { TimeEntriesFilterParams } from '@/features/time-entries/types'

interface TimeEntriesStatsPanelsProps {
  params: TimeEntriesFilterParams
  enabled?: boolean
  className?: string
}

export function TimeEntriesStatsPanels({ params, enabled = true, className }: TimeEntriesStatsPanelsProps) {
  const { overview, isOverviewLoading, pulse, isPulseLoading } = useTimeEntriesStats({ params, enabled })

  return (
    <div className={cn('grid min-w-0 gap-3 lg:grid-cols-[minmax(0,1.65fr)_minmax(320px,0.95fr)]', className)}>
      <TimeEntriesOverviewTiles overview={overview} isLoading={isOverviewLoading} />
      <TimeEntriesPulsePanel pulse={pulse} isLoading={isPulseLoading} />
    </div>
  )
}
