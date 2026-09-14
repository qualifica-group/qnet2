/**
 * One day of the dashboard list (spec 0122 AC-035/AC-036), replicating
 * q-net's `WorkActivityDayCard` layout: a status/date rail on the left, the
 * workload composition + day note + (when expanded) entries table on the
 * right. Today is always expanded and has no collapse control.
 */

import { useTranslation } from 'react-i18next'
import { ChevronDown, ChevronUp, Plus } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { DAILY_STATUS_META, dailyStatusBadgeClass } from '@/features/time-entries/time-entry-constants'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import { parseDateString } from '@/features/time-entries/time-entry-period'
import { TimeEntryDayComposition } from '@/features/time-entries/days/time-entry-day-composition'
import { TimeEntryDayEntriesTable } from '@/features/time-entries/days/time-entry-day-entries-table'
import { TimeEntryDayNote } from '@/features/time-entries/days/time-entry-day-note'
import type { DaySummary } from '@/features/time-entries/types'

interface TimeEntryDayCardProps {
  day: DaySummary
  isToday: boolean
  isExpanded: boolean
  onToggle: () => void
  canWrite: boolean
  /** The dashboard's selected user (D-8), forwarded to the day note's payload. */
  selectedUserId?: number
  onEditEntry: (entryId: number) => void
  onCreateForDate: (date: string) => void
}

/** Capitalized long weekday name in the app's active language. */
function weekdayLabel(date: string, locale: string): string {
  const parsed = parseDateString(date)
  if (!parsed) {
    return ''
  }
  const label = new Intl.DateTimeFormat(locale, { weekday: 'long' }).format(parsed)
  return label.charAt(0).toUpperCase() + label.slice(1)
}

export function TimeEntryDayCard({
  day,
  isToday,
  isExpanded,
  onToggle,
  canWrite,
  selectedUserId,
  onEditEntry,
  onCreateForDate,
}: TimeEntryDayCardProps) {
  const { t, i18n } = useTranslation()
  const statusMeta = DAILY_STATUS_META[day.status]
  const StatusIcon = statusMeta.icon
  const canToggle = !isToday

  const handleCompositionKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (canToggle && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault()
      onToggle()
    }
  }

  return (
    <Card className="gap-0 overflow-hidden py-0" data-day-card>
      <CardContent className="p-0">
        <div className="grid gap-0 md:grid-cols-[240px_minmax(0,1fr)]">
          <div className="border-b border-border bg-muted/15 p-4 md:border-r md:border-b-0">
            <div className="flex items-start justify-between gap-3">
              <Badge className={cn('gap-1.5', dailyStatusBadgeClass(day.status))} variant="outline">
                <StatusIcon aria-hidden="true" className="size-3.5" />
                {t(`timeEntries.dailyStatus.${statusMeta.labelKey}`)}
              </Badge>
              {canToggle ? (
                <Button
                  aria-label={isExpanded ? t('timeEntries.dayCard.collapse') : t('timeEntries.dayCard.expand')}
                  onClick={onToggle}
                  size="icon-sm"
                  type="button"
                  variant="ghost"
                >
                  {isExpanded ? <ChevronUp className="size-4" /> : <ChevronDown className="size-4" />}
                </Button>
              ) : null}
            </div>

            <div className="mt-4">
              <div className="text-lg font-semibold text-foreground">{formatDate(day.date)}</div>
              <div className="text-sm text-muted-foreground">{weekdayLabel(day.date, i18n.language)}</div>
              <div className="mt-2">
                <Badge
                  className={cn('gap-1.5', badgeColorClass(day.is_active ? 'emerald' : 'slate'))}
                  variant="outline"
                >
                  <span className={cn('size-2 rounded-full', day.is_active ? 'bg-emerald-500' : 'bg-slate-400')} />
                  {t(`timeEntries.filters.isActiveOptions.${day.is_active}`)}
                </Badge>
              </div>
              <div className="mt-3 flex flex-wrap items-center gap-2">
                <div className="flex items-center gap-1.5 rounded-md border border-border bg-card px-2 py-1 text-[10px] font-medium text-foreground">
                  <span className="text-muted-foreground">{t('timeEntries.kpi.tracked')}</span>
                  <span>{formatMinutesLabel(day.total_minutes)}</span>
                </div>
                <div className="flex items-center gap-1.5 rounded-md border border-border bg-card px-2 py-1 text-[10px] font-medium text-foreground">
                  <span className="text-muted-foreground">{t('timeEntries.dayCard.utilization')}</span>
                  <span>{Math.round(day.utilization_percentage)}%</span>
                </div>
              </div>
            </div>
          </div>

          <div className={cn('min-w-0', isExpanded ? 'p-4' : 'px-3 py-2')}>
            <div
              className={cn(
                isExpanded ? 'rounded-2xl border border-border bg-card p-4' : 'border-0 bg-transparent px-1 py-1',
                canToggle ? 'cursor-pointer' : undefined,
              )}
              onClick={canToggle ? onToggle : undefined}
              onKeyDown={canToggle ? handleCompositionKeyDown : undefined}
              role={canToggle ? 'button' : undefined}
              tabIndex={canToggle ? 0 : undefined}
            >
              <TimeEntryDayComposition
                canCreate={canWrite}
                day={day}
                isExpanded={isExpanded}
                onCreateForDate={() => onCreateForDate(day.date)}
              />
            </div>

            {canWrite || day.day_note ? (
              <div className="mt-3" onClick={(event) => event.stopPropagation()}>
                <TimeEntryDayNote
                  canWrite={canWrite}
                  date={day.date}
                  note={day.day_note}
                  selectedUserId={selectedUserId}
                />
              </div>
            ) : null}

            {isExpanded ? (
              <div className="mt-4">
                {canWrite ? (
                  <div className="mb-3 flex justify-end">
                    <Button onClick={() => onCreateForDate(day.date)} size="sm" type="button">
                      <Plus aria-hidden="true" className="size-4" />
                      {t('timeEntries.page.newTimeEntry')}
                    </Button>
                  </div>
                ) : null}
                <TimeEntryDayEntriesTable canWrite={canWrite} entries={day.entries} onEditEntry={onEditEntry} />
              </div>
            ) : null}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

/** Loading placeholder shaped like a collapsed day card, shown before the first page resolves. */
export function TimeEntryDayCardSkeleton() {
  return (
    <Card className="gap-0 overflow-hidden py-0">
      <CardContent className="p-0">
        <div className="grid gap-0 md:grid-cols-[240px_minmax(0,1fr)]">
          <div className="border-b border-border bg-muted/15 p-4 md:border-r md:border-b-0">
            <div className="flex items-start justify-between gap-3">
              <Skeleton className="h-5 w-24 rounded-full" />
              <Skeleton className="size-7 rounded-md" />
            </div>
            <div className="mt-4 space-y-2">
              <Skeleton className="h-6 w-28" />
              <Skeleton className="h-3 w-20" />
              <Skeleton className="h-5 w-20 rounded-full" />
              <div className="flex flex-wrap gap-2 pt-1">
                <Skeleton className="h-6 w-20 rounded-md" />
                <Skeleton className="h-6 w-20 rounded-md" />
              </div>
            </div>
          </div>
          <div className="p-4">
            <div className="rounded-2xl border border-border bg-card p-4">
              <Skeleton className="mb-3 h-4 w-32" />
              <Skeleton className="mb-2 h-3 w-full" />
              <Skeleton className="h-3 w-full rounded-full" />
              <div className="mt-3 flex gap-3">
                <Skeleton className="h-3 w-16" />
                <Skeleton className="h-3 w-16" />
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
