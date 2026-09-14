/**
 * Pure day-grouping of a Task's flat segnatempo list (spec 0122 MT-F6, D-9;
 * `TaskTimeEntriesResponse.items` order: date desc, start_time desc, id
 * desc). Mirrors q-net's `TaskTimeTrackingEntriesList` flow: one group per
 * date (label "Oggi"/"Ieri"/formatted date), a per-day total, and a helper to
 * collapse the whole list to its first N entries overall — cutting mid-group
 * when needed — for the "Mostra tutti/Mostra meno" toggle (AC-040). No React
 * here so the grouping/collapse math is independently testable.
 */

import type { TFunction } from 'i18next'
import { formatDate } from '@/lib/formatting/date-display'
import { getTodayDateKey, parseDateString, toDateString } from '@/features/time-entries/time-entry-period'
import type { TimeEntry } from '@/features/time-entries/types'

export interface TaskTimeEntryGroup {
  dateKey: string
  label: string
  totalMinutes: number
  entries: TimeEntry[]
}

function yesterdayDateKey(): string {
  const today = parseDateString(getTodayDateKey()) as Date
  const yesterday = new Date(today)
  yesterday.setDate(today.getDate() - 1)
  return toDateString(yesterday)
}

function groupLabel(dateKey: string, t: TFunction): string {
  if (dateKey === getTodayDateKey()) {
    return t('timeEntries.task.today')
  }
  if (dateKey === yesterdayDateKey()) {
    return t('timeEntries.task.yesterday')
  }
  return formatDate(dateKey)
}

/** Groups already-sorted entries by their own `date`, preserving the incoming order. */
export function groupTaskTimeEntriesByDay(entries: TimeEntry[], t: TFunction): TaskTimeEntryGroup[] {
  const groups: TaskTimeEntryGroup[] = []
  const indexByDate = new Map<string, number>()

  for (const entry of entries) {
    const existingIndex = indexByDate.get(entry.date)
    if (existingIndex !== undefined) {
      const group = groups[existingIndex]
      group.entries.push(entry)
      group.totalMinutes += entry.minutes
      continue
    }
    indexByDate.set(entry.date, groups.length)
    groups.push({ dateKey: entry.date, label: groupLabel(entry.date, t), totalMinutes: entry.minutes, entries: [entry] })
  }

  return groups
}

/** Keeps only the first `limit` entries overall, cutting a group short rather than dropping it whole. */
export function limitTaskTimeEntryGroups(groups: TaskTimeEntryGroup[], limit: number): TaskTimeEntryGroup[] {
  const limited: TaskTimeEntryGroup[] = []
  let remaining = limit

  for (const group of groups) {
    if (remaining <= 0) {
      break
    }
    const entries = group.entries.slice(0, remaining)
    remaining -= entries.length
    limited.push({
      ...group,
      entries,
      totalMinutes: entries.reduce((sum, entry) => sum + entry.minutes, 0),
    })
  }

  return limited
}
