/**
 * Pure presentation math for one `DaySummary` (spec 0122 D-7/D-14, AC-035).
 * The backend already computes `total_minutes`/`target_minutes`/
 * `utilization_percentage`/`status` — this module only derives what the
 * bar/legend need FROM the day's own `entries`: the hour scale, the target
 * line offset and the per-`task_type` segments/distribution. No React here,
 * so the numbers are independently testable.
 */

import { Clock, type LucideIcon } from 'lucide-react'
import { ICON_CATALOG, isKnownIconName } from '@/features/custom-fields/icon-catalog'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import type { DaySummary, TaskTypeRef } from '@/features/time-entries/types'

/** A segment never renders thinner than this, so a short entry stays visible in the bar. */
const MIN_SEGMENT_WIDTH_PERCENT = 2

/** Neutral glyph for a task type whose `icon` is null or outside the curated catalogue. */
const FALLBACK_TASK_TYPE_ICON: LucideIcon = Clock

/** Resolves a task type's stored lucide name to its component, with a stable fallback. */
export function resolveTaskTypeIcon(icon: string | null): LucideIcon {
  return isKnownIconName(icon) ? ICON_CATALOG[icon] : FALLBACK_TASK_TYPE_ICON
}

export interface DayHourMark {
  leftPercent: number
  label: string
}

export interface DaySegment {
  taskTypeId: number
  label: string
  icon: LucideIcon
  widthPercent: number
  barClassName: string | undefined
}

export interface DayDistributionEntry {
  taskTypeId: number
  label: string
  icon: LucideIcon
  minutes: number
  percentage: number
  barClassName: string | undefined
}

export interface DayComposition {
  scaleCeilingMinutes: number
  hourMarks: DayHourMark[]
  /** `null` on a `no_target` day (D-7): the vertical target line has nothing to mark. */
  targetOffsetPercent: number | null
  segments: DaySegment[]
  distribution: DayDistributionEntry[]
  /** `round(total/target*100)`, `null` when `target_minutes` is 0 (AC-035). */
  totalOverTargetPercent: number | null
}

interface TaskTypeMinutes {
  taskType: TaskTypeRef
  minutes: number
}

/** Sums each entry's minutes into its `task_type`, one bucket per type id. */
function groupMinutesByTaskType(day: DaySummary): TaskTypeMinutes[] {
  const totals = new Map<number, TaskTypeMinutes>()
  for (const entry of day.entries) {
    const bucket = totals.get(entry.task_type.id)
    totals.set(entry.task_type.id, {
      taskType: entry.task_type,
      minutes: (bucket?.minutes ?? 0) + entry.minutes,
    })
  }
  return Array.from(totals.values()).sort((left, right) => right.minutes - left.minutes)
}

/** One tick per hour of the scale, evenly spaced left to right (`0h`..`Nh`). */
export function buildHourMarks(scaleCeilingMinutes: number): DayHourMark[] {
  const hourCount = Math.max(1, Math.round(scaleCeilingMinutes / 60))
  return Array.from({ length: hourCount + 1 }, (_, index) => ({
    leftPercent: (index / hourCount) * 100,
    label: `${index}h`,
  }))
}

/** Builds the bar/legend/target-line model for one day, from its `entries` (AC-035). */
export function buildDayComposition(day: DaySummary): DayComposition {
  const grouped = groupMinutesByTaskType(day)
  const scaleCeilingMinutes =
    Math.ceil(Math.max(day.target_minutes, day.total_minutes, 60) / 60) * 60

  const segments: DaySegment[] = grouped.map(({ taskType, minutes }) => ({
    taskTypeId: taskType.id,
    label: taskType.name,
    icon: resolveTaskTypeIcon(taskType.icon),
    widthPercent: Math.max(MIN_SEGMENT_WIDTH_PERCENT, (minutes / scaleCeilingMinutes) * 100),
    barClassName: swatchClassFor(taskType.color),
  }))

  const distribution: DayDistributionEntry[] = grouped.map(({ taskType, minutes }) => ({
    taskTypeId: taskType.id,
    label: taskType.name,
    icon: resolveTaskTypeIcon(taskType.icon),
    minutes,
    percentage: Math.round((minutes / Math.max(day.total_minutes, 1)) * 100),
    barClassName: swatchClassFor(taskType.color),
  }))

  return {
    scaleCeilingMinutes,
    hourMarks: buildHourMarks(scaleCeilingMinutes),
    targetOffsetPercent:
      day.target_minutes > 0 ? Math.min(100, (day.target_minutes / scaleCeilingMinutes) * 100) : null,
    segments,
    distribution,
    totalOverTargetPercent:
      day.target_minutes > 0 ? Math.round((day.total_minutes / day.target_minutes) * 100) : null,
  }
}
