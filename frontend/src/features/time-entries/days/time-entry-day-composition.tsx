/**
 * Workload composition block of a day card (spec 0122 AC-035): hour scale,
 * target line, one bar segment per `task_type`, and the legend. Purely
 * presentational — `time-entry-day-view-model.ts` does the math, the parent
 * `TimeEntryDayCard` owns the expand/collapse click on the wrapping block.
 */

import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { buildDayComposition } from '@/features/time-entries/days/time-entry-day-view-model'
import { TimeEntryDistributionLegend } from '@/features/time-entries/days/time-entry-distribution-legend'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { DaySummary } from '@/features/time-entries/types'

interface TimeEntryDayCompositionProps {
  day: DaySummary
  isExpanded: boolean
  canCreate: boolean
  onCreateForDate: () => void
}

export function TimeEntryDayComposition({
  day,
  isExpanded,
  canCreate,
  onCreateForDate,
}: TimeEntryDayCompositionProps) {
  const { t } = useTranslation()
  const composition = buildDayComposition(day)

  return (
    <div>
      <div className="mb-3 flex items-center justify-between gap-3">
        <div className="text-sm font-semibold text-foreground">
          {t('timeEntries.dayCard.compositionTitle')}
        </div>
        <div className="text-sm font-semibold text-foreground">
          {t('timeEntries.dayCard.total')}: {formatMinutesLabel(day.total_minutes)}
          {day.target_minutes > 0 ? (
            <>
              {' '}
              / {formatMinutesLabel(day.target_minutes)}
              <span className="ml-1 text-xs font-medium text-muted-foreground">
                ({composition.totalOverTargetPercent}%)
              </span>
            </>
          ) : null}
        </div>
      </div>

      <div className="relative mb-3 h-4 text-[11px] text-muted-foreground">
        {composition.hourMarks.map((mark) => (
          <span
            className="absolute top-0 -translate-x-1/2"
            key={mark.label}
            style={{ left: `${mark.leftPercent}%` }}
          >
            {mark.label}
          </span>
        ))}
      </div>

      <div
        className={cn(
          'relative overflow-hidden rounded-full bg-muted/30',
          isExpanded ? 'border border-border' : 'border-0',
        )}
      >
        {composition.targetOffsetPercent !== null ? (
          <div
            className="absolute inset-y-0 z-10 w-px bg-foreground/30"
            style={{ left: `${composition.targetOffsetPercent}%` }}
          />
        ) : null}
        <div className="flex h-4 w-full">
          {composition.segments.map((segment) => (
            <div
              className={segment.barClassName}
              key={segment.taskTypeId}
              style={{ width: `${segment.widthPercent}%` }}
            />
          ))}
          <div className="flex-1 bg-muted/60" />
        </div>
      </div>

      <div
        className={cn(
          'mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground',
          isExpanded ? '' : 'items-center justify-between gap-2',
        )}
      >
        <TimeEntryDistributionLegend distribution={composition.distribution} />
        {!isExpanded && canCreate ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                aria-label={t('timeEntries.page.newTimeEntry')}
                className="size-6 p-0"
                onClick={(event) => {
                  event.stopPropagation()
                  onCreateForDate()
                }}
                type="button"
                variant="default"
              >
                <Plus className="size-3" aria-hidden="true" />
              </Button>
            </TooltipTrigger>
            <TooltipContent side="top">{t('timeEntries.page.newTimeEntry')}</TooltipContent>
          </Tooltip>
        ) : null}
      </div>
    </div>
  )
}
