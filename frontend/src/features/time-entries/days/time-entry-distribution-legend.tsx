/**
 * Legend of a day's workload composition bar (spec 0122 AC-035): one
 * icon+label+percentage entry per `task_type`, mirroring q-net's
 * `WorkActivityDistributionLegend`.
 */

import { useTranslation } from 'react-i18next'
import type { DayDistributionEntry } from '@/features/time-entries/days/time-entry-day-view-model'

interface TimeEntryDistributionLegendProps {
  distribution: DayDistributionEntry[]
}

export function TimeEntryDistributionLegend({ distribution }: TimeEntryDistributionLegendProps) {
  const { t } = useTranslation()

  if (distribution.length === 0) {
    return <span>{t('timeEntries.dayCard.noEntries')}</span>
  }

  return (
    <>
      {distribution.map((entry) => {
        const Icon = entry.icon
        return (
          <span className="inline-flex items-center gap-1.5" key={entry.taskTypeId}>
            <Icon className="size-3.5 text-muted-foreground" aria-hidden="true" />
            <span>
              {entry.label} {entry.percentage}%
            </span>
          </span>
        )
      })}
    </>
  )
}
