/**
 * Pure timeline geometry of the Task board's Roadmap view (user request
 * 2026-11-25, Planner-style): the visible date window, its header columns and
 * groups, and where each task's bar sits in it, in DAYS from the window's first
 * day; `roadmapPx` turns days into pixels at the timeline's scale. Three scales
 * (Settimane / Mesi / Anno) differ only in their `RoadmapScaleConfig`. Nothing
 * here touches the DOM.
 *
 * Every date is a `Y-m-d` handled as a UTC midnight, so no step shifts by a
 * day with the runner's timezone (same reasoning as `task-board-filters.ts`).
 */

import type { BoardTask } from '@/features/work-orders/task-board/types'

const MS_PER_DAY = 86_400_000
const DAYS_PER_WEEK = 7

export type RoadmapScale = 'week' | 'month' | 'year'

export const ROADMAP_SCALES: readonly RoadmapScale[] = ['week', 'month', 'year']

/** A calendar unit the window snaps to, the header columns and groups are cut by. */
type CalendarUnit = 'week' | 'month' | 'year'

export interface RoadmapScaleConfig {
  /** Width of one day on screen: the only pixel value of a scale, everything else is counted in days. */
  dayWidthPx: number
  /** The window starts and ends on this unit's boundaries, padded and widened in this unit. */
  windowUnit: CalendarUnit
  paddingUnits: number
  /** The window never shrinks below this many units, so a commessa with few dated tasks still reads as a timeline. */
  minUnits: number
  /** One header column (and one grid line) per this unit... */
  columnUnit: Exclude<CalendarUnit, 'year'>
  /** ...under a header group per this one (the month above the weeks, the year above the months). */
  groupUnit: Exclude<CalendarUnit, 'week'>
  /** On open the timeline scrolls so today sits this many days from its left edge: the recent past stays in view. */
  daysBeforeTodayOnOpen: number
}

/** Week 98px, month about 150px (Mesi) or 60px (Anno): the labels fit and each scale reads at a different zoom. */
export const ROADMAP_SCALE_CONFIGS: Record<RoadmapScale, RoadmapScaleConfig> = {
  week: { dayWidthPx: 14, windowUnit: 'week', paddingUnits: 1, minUnits: 8, columnUnit: 'week', groupUnit: 'month', daysBeforeTodayOnOpen: 14 },
  month: { dayWidthPx: 5, windowUnit: 'month', paddingUnits: 1, minUnits: 6, columnUnit: 'month', groupUnit: 'year', daysBeforeTodayOnOpen: 31 },
  year: { dayWidthPx: 2, windowUnit: 'year', paddingUnits: 0, minUnits: 1, columnUnit: 'month', groupUnit: 'year', daysBeforeTodayOnOpen: 92 },
}

function parseUtcDate(date: string): Date {
  const [year, month, day] = date.split('-').map(Number)
  return new Date(Date.UTC(year, month - 1, day))
}

function formatUtcDate(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function addDays(date: string, days: number): string {
  const parsed = parseUtcDate(date)
  parsed.setUTCDate(parsed.getUTCDate() + days)
  return formatUtcDate(parsed)
}

/** Whole days from `from` to `to` (negative when `to` comes first). */
export function daysBetween(from: string, to: string): number {
  return Math.round((parseUtcDate(to).getTime() - parseUtcDate(from).getTime()) / MS_PER_DAY)
}

/** The first day of the unit holding `date`: its Monday, the 1st of its month, or the 1st of January. */
function startOfUnit(date: string, unit: CalendarUnit): string {
  if (unit === 'week') {
    const isoWeekday = parseUtcDate(date).getUTCDay() || DAYS_PER_WEEK
    return addDays(date, 1 - isoWeekday)
  }

  return unit === 'month' ? `${date.slice(0, 7)}-01` : `${date.slice(0, 4)}-01-01`
}

/** Moves a unit START by whole units (on any other day a month step could overflow into the next month). */
function addUnits(unitStart: string, unit: CalendarUnit, count: number): string {
  if (unit === 'week') {
    return addDays(unitStart, count * DAYS_PER_WEEK)
  }
  const parsed = parseUtcDate(unitStart)
  if (unit === 'month') {
    parsed.setUTCMonth(parsed.getUTCMonth() + count)
  } else {
    parsed.setUTCFullYear(parsed.getUTCFullYear() + count)
  }

  return formatUtcDate(parsed)
}

/**
 * The dated stretch of a task: both dates make a bar, a single one a
 * one-day milestone on that day; `null` when the task has no date at all.
 * Inverted dates (never accepted by the server) are read in calendar order.
 */
export interface RoadmapSpan {
  start: string
  end: string
  isMilestone: boolean
}

export function roadmapSpan(task: Pick<BoardTask, 'start_date' | 'end_date'>): RoadmapSpan | null {
  const { start_date: start, end_date: end } = task
  if (start === null && end === null) {
    return null
  }
  if (start === null || end === null) {
    const only = (start ?? end) as string
    return { start: only, end: only, isMilestone: true }
  }

  return start <= end ? { start, end, isMilestone: false } : { start: end, end: start, isMilestone: false }
}

/** The visible window: `start` on a unit boundary of its scale, `days` long (always whole units). */
export interface RoadmapRange {
  start: string
  days: number
}

/**
 * Covers every dated task AND today (the today line must always be on
 * screen), snapped to whole units of the scale, padded on both sides and
 * widened to the scale's minimum.
 */
export function buildRoadmapRange(
  tasks: Pick<BoardTask, 'start_date' | 'end_date'>[],
  today: string,
  scale: RoadmapScale,
): RoadmapRange {
  const { windowUnit, paddingUnits, minUnits } = ROADMAP_SCALE_CONFIGS[scale]

  // Step 1: earliest and latest date among the dated tasks and today
  let earliest = today
  let latest = today
  for (const task of tasks) {
    const span = roadmapSpan(task)
    if (span === null) {
      continue
    }
    earliest = span.start < earliest ? span.start : earliest
    latest = span.end > latest ? span.end : latest
  }

  // Step 2: snap to whole units with the padding on both sides, then widen to the minimum
  const start = addUnits(startOfUnit(earliest, windowUnit), windowUnit, -paddingUnits)
  const paddedEnd = addUnits(startOfUnit(latest, windowUnit), windowUnit, paddingUnits + 1)
  const minimumEnd = addUnits(start, windowUnit, minUnits)
  const end = paddedEnd > minimumEnd ? paddedEnd : minimumEnd

  return { start, days: daysBetween(start, end) }
}

/** The timeline's geometry, computed once by the view. */
export interface RoadmapTimeline {
  range: RoadmapRange
  /** Days from the window start to today. */
  todayOffset: number
  dayWidthPx: number
  /** The header columns, also the timeline's vertical grid lines. */
  columns: RoadmapSlice[]
}

export function roadmapPx(timeline: RoadmapTimeline, days: number): number {
  return days * timeline.dayWidthPx
}

/**
 * One calendar unit of the header, clipped to the window: `start` is the
 * unit's own first day (it labels the slice even when the window cuts in
 * after it), `offset` and `days` the part inside the window.
 */
export interface RoadmapSlice {
  start: string
  offset: number
  days: number
}

export function roadmapSlices(range: RoadmapRange, unit: CalendarUnit): RoadmapSlice[] {
  const end = addDays(range.start, range.days)
  const slices: RoadmapSlice[] = []
  for (let cursor = startOfUnit(range.start, unit); cursor < end; cursor = addUnits(cursor, unit, 1)) {
    const next = addUnits(cursor, unit, 1)
    const from = cursor < range.start ? range.start : cursor
    const to = next < end ? next : end
    slices.push({ start: cursor, offset: daysBetween(range.start, from), days: daysBetween(from, to) })
  }

  return slices
}

/** A bar inside the window: `offset` days from its start, `length` days long (both ends included). */
export interface RoadmapBar {
  offset: number
  length: number
}

export function roadmapBar(span: RoadmapSpan, range: RoadmapRange): RoadmapBar {
  return { offset: daysBetween(range.start, span.start), length: daysBetween(span.start, span.end) + 1 }
}

/** The overall stretch of a set of tasks (a fase's summary bar); `null` when none is dated. */
export function mergeSpans(spans: (RoadmapSpan | null)[]): RoadmapSpan | null {
  const dated = spans.filter((span): span is RoadmapSpan => span !== null)
  if (dated.length === 0) {
    return null
  }
  const start = dated.reduce((min, span) => (span.start < min ? span.start : min), dated[0].start)
  const end = dated.reduce((max, span) => (span.end > max ? span.end : max), dated[0].end)

  return { start, end, isMilestone: start === end }
}
