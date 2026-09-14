/**
 * Grouped-by-day list of a Task's segnatempo entries (spec 0122 MT-F6, D-9):
 * replicates q-net's `TaskTimeTrackingEntriesList` flow — group by date
 * (Oggi/Ieri/date), a per-day total, collapse past 3 entries overall with a
 * toggle. The row menu delegates to the dashboard's own `EntryActionsCell`
 * (D-14): same Modifica/Elimina gating on `entry.permissions`, no duplicate.
 */

import { createElement, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Clock } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { TooltipProvider } from '@/components/ui/tooltip'
import { formatDate } from '@/lib/formatting/date-display'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { TIME_ENTRY_DAY_ENTRIES_COLLAPSE_THRESHOLD } from '@/features/time-entries/time-entry-constants'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import { EntryActionsCell } from '@/features/time-entries/days/time-entry-entry-cells'
import { resolveTaskTypeIcon } from '@/features/time-entries/days/time-entry-day-view-model'
import {
  groupTaskTimeEntriesByDay,
  limitTaskTimeEntryGroups,
} from '@/features/time-entries/task/task-time-entries-grouping'
import type { TimeEntry } from '@/features/time-entries/types'
import { cn } from '@/lib/utils'

function renderTypeIcon(icon: string | null) {
  return createElement(resolveTaskTypeIcon(icon), { 'aria-hidden': 'true', className: 'size-4' })
}

interface TaskTimeEntriesListProps {
  entries: TimeEntry[]
  isLoading: boolean
  onEditEntry: (entryId: number) => void
  onRequestDelete: (entry: TimeEntry) => void
}

export function TaskTimeEntriesList({ entries, isLoading, onEditEntry, onRequestDelete }: TaskTimeEntriesListProps) {
  const { t } = useTranslation()
  const [isExpanded, setIsExpanded] = useState(false)

  const groups = useMemo(() => groupTaskTimeEntriesByDay(entries, t), [entries, t])
  const shouldCollapse = entries.length > TIME_ENTRY_DAY_ENTRIES_COLLAPSE_THRESHOLD
  const visibleGroups =
    shouldCollapse && !isExpanded
      ? limitTaskTimeEntryGroups(groups, TIME_ENTRY_DAY_ENTRIES_COLLAPSE_THRESHOLD)
      : groups

  if (isLoading) {
    return (
      <div className="space-y-2 rounded-xl border bg-card p-4 shadow-sm" aria-hidden="true">
        <Skeleton className="h-14 w-full" />
        <Skeleton className="h-14 w-full" />
        <Skeleton className="h-14 w-full" />
      </div>
    )
  }

  if (entries.length === 0) {
    return (
      <div className="flex items-center justify-center gap-2 rounded-xl border bg-card p-4 text-center text-sm text-muted-foreground shadow-sm">
        <Clock className="size-4" aria-hidden="true" />
        <span>{t('timeEntries.task.noInterval')}</span>
      </div>
    )
  }

  return (
    <div className="rounded-xl border bg-card p-4 shadow-sm">
      <div className="grid gap-4">
        {visibleGroups.map((group) => (
          <div className="space-y-2" key={group.dateKey}>
            <div className="flex items-center justify-between gap-3 border-b pb-2">
              <div className="text-sm font-semibold">{group.label}</div>
              <div className="text-xs font-medium text-muted-foreground">
                {formatMinutesLabel(group.totalMinutes)}
              </div>
            </div>
            <TooltipProvider>
              <div className="overflow-hidden rounded-md border">
                {group.entries.map((entry, index) => (
                  <TaskTimeEntryRow
                    key={entry.id}
                    entry={entry}
                    isFirst={index === 0}
                    onEditEntry={onEditEntry}
                    onRequestDelete={onRequestDelete}
                  />
                ))}
              </div>
            </TooltipProvider>
          </div>
        ))}
        {shouldCollapse ? (
          <div className="flex justify-center pt-1">
            <Button
              type="button"
              variant="outline"
              className="bg-card"
              size="sm"
              onClick={() => setIsExpanded((current) => !current)}
            >
              {isExpanded ? t('timeEntries.task.showFewer') : t('timeEntries.task.showAll')}
            </Button>
          </div>
        ) : null}
      </div>
    </div>
  )
}

interface TaskTimeEntryRowProps {
  entry: TimeEntry
  isFirst: boolean
  onEditEntry: (entryId: number) => void
  onRequestDelete: (entry: TimeEntry) => void
}

function TaskTimeEntryRow({ entry, isFirst, onEditEntry, onRequestDelete }: TaskTimeEntryRowProps) {
  const { t } = useTranslation()
  const scheduleLabel = entry.start_time && entry.end_time ? `${entry.start_time} - ${entry.end_time}` : null

  return (
    <div className="px-3">
      {!isFirst ? <div className="mb-3 border-t" /> : null}
      <div className="flex items-center gap-3 py-3">
        <span
          className={cn(
            'inline-flex size-9 shrink-0 items-center justify-center rounded-lg border',
            badgeColorClass(entry.task_type.color),
          )}
        >
          {renderTypeIcon(entry.task_type.icon)}
        </span>
        <div className="min-w-0 flex-1">
          <div className="flex items-center justify-between gap-3">
            <div className="min-w-0 flex-1">
              <p className="text-sm text-muted-foreground">
                <span className="mr-1 text-[10px] font-semibold uppercase tracking-[0.08em]">
                  {t('timeEntries.task.noteLabel')}
                </span>
                <span>{entry.notes?.trim() || '-'}</span>
              </p>
              <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                <div className="inline-flex items-center gap-1">
                  <span className="font-medium">{t('timeEntries.task.dateLabel')}</span>
                  <span>{formatDate(entry.date)}</span>
                </div>
                <div className="inline-flex items-center gap-1">
                  <span className="font-medium">{t('timeEntries.task.creatorLabel')}</span>
                  <span>{entry.user.name}</span>
                </div>
              </div>
            </div>

            <div className="flex shrink-0 items-center gap-2">
              <div className="text-right">
                {scheduleLabel ? <div className="text-sm font-semibold text-foreground">{scheduleLabel}</div> : null}
                <div className="text-sm text-muted-foreground">{formatMinutesLabel(entry.minutes)}</div>
              </div>
              <EntryActionsCell
                actionsLabel={t('timeEntries.table.actions')}
                canWrite
                deleteLabel={t('timeEntries.table.delete')}
                editLabel={t('timeEntries.table.edit')}
                entry={entry}
                onEditEntry={onEditEntry}
                onRequestDelete={onRequestDelete}
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
