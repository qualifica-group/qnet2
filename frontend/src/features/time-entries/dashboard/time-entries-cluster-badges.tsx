/**
 * The percentage + minutes pill pair shown next to a cluster's icon/label
 * (spec 0122 D-11), reused by the pulse panel's primary cluster and by the
 * "Other clusters" popover rows (`size="sm"` there).
 */

import { BarChart3, Clock } from 'lucide-react'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import { cn } from '@/lib/utils'

interface TimeEntriesClusterBadgesProps {
  percentage: number
  minutes: number
  size?: 'sm' | 'md'
}

export function TimeEntriesClusterBadges({
  percentage,
  minutes,
  size = 'md',
}: TimeEntriesClusterBadgesProps) {
  const sizeClass = size === 'sm' ? 'px-1.5 py-0.5 text-[11px]' : 'px-2 py-0.5 text-xs'

  return (
    <div className="flex flex-wrap items-center justify-center gap-1.5">
      <span
        className={cn(
          'inline-flex items-center gap-1 rounded-full bg-muted font-semibold text-foreground',
          sizeClass,
        )}
      >
        <BarChart3 className="size-3" aria-hidden="true" />
        {percentage}%
      </span>
      <span
        className={cn(
          'inline-flex items-center gap-1 rounded-full bg-sky-100 font-semibold text-sky-800 dark:bg-sky-900/50 dark:text-sky-200',
          sizeClass,
        )}
      >
        <Clock className="size-3" aria-hidden="true" />
        {formatMinutesLabel(minutes)}
      </span>
    </div>
  )
}
