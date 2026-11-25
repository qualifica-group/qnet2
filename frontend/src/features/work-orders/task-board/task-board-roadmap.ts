/**
 * Pure timeline geometry of the Task board's Roadmap view (user request
 * 2026-11-25, Planner-style): the visible date window, its week columns and
 * where each task's bar sits in it, in DAYS from the window's first day;
 * `roadmapPx` turns days into pixels. Nothing here touches the DOM.
 *
 * Every date is a `Y-m-d` handled as a UTC midnight, so no step shifts by a
 * day with the runner's timezone (same reasoning as `task-board-filters.ts`).
 */

import type { BoardTask } from '@/features/work-orders/task-board/types'

const MS_PER_DAY = 86_400_000
const DAYS_PER_WEEK = 7

/** Width of one day on screen: the only pixel value of the view, everything else is counted in days. */
export const ROADMAP_DAY_WIDTH_PX = 14

export function roadmapPx(days: number): number {
  return days * ROADMAP_DAY_WIDTH_PX
}

/** The window never shrinks below this many weeks, so a commessa with few dated tasks still reads as a timeline. */
export const ROADMAP_MIN_WEEKS = 8

/** The window opens one week before the earliest date and closes one week after the latest: bars never touch the edges. */
const ROADMAP_PADDING_WEEKS = 1

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

function mondayOf(date: string): string {
  const isoWeekday = parseUtcDate(date).getUTCDay() || DAYS_PER_WEEK
  return addDays(date, 1 - isoWeekday)
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

/** The visible window: Monday `start`, `days` long (always whole weeks). */
export interface RoadmapRange {
  start: string
  days: number
}

/**
 * Covers every dated task AND today (the today line must always be on
 * screen), padded by a week on both sides and widened to `ROADMAP_MIN_WEEKS`.
 */
export function buildRoadmapRange(tasks: Pick<BoardTask, 'start_date' | 'end_date'>[], today: string): RoadmapRange {
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

  // Step 2: snap to whole weeks with the padding on both sides
  const start = addDays(mondayOf(earliest), -ROADMAP_PADDING_WEEKS * DAYS_PER_WEEK)
  const lastMonday = addDays(mondayOf(latest), ROADMAP_PADDING_WEEKS * DAYS_PER_WEEK)
  const weeks = Math.max(daysBetween(start, lastMonday) / DAYS_PER_WEEK + 1, ROADMAP_MIN_WEEKS)

  return { start, days: weeks * DAYS_PER_WEEK }
}

/** The timeline's geometry, computed once by the view. */
export interface RoadmapTimeline {
  range: RoadmapRange
  /** Days from the window start to today. */
  todayOffset: number
}

/** One week column of the header: its Monday and its offset in days from the window start. */
export interface RoadmapWeek {
  monday: string
  offset: number
}

export function roadmapWeeks(range: RoadmapRange): RoadmapWeek[] {
  return Array.from({ length: range.days / DAYS_PER_WEEK }, (_, index) => ({
    monday: addDays(range.start, index * DAYS_PER_WEEK),
    offset: index * DAYS_PER_WEEK,
  }))
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
