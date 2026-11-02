/**
 * Pure maths of the bulk "Completa" time-entry split (spec 0338): the
 * segnatempo typed in the dialog is a TOTAL, divided among the selected tasks.
 * The server re-checks everything (it is the authority on the sum); this only
 * previews and pre-validates.
 */
import { timeValueToMinutes } from '@/features/time-entries/time-entry-format'

/** One task's slice of the total, in the order sent to the server (which is the order of the consecutive slots). */
export interface TimeEntryShare {
  task_id: number
  minutes: number
}

export interface ShareSlot {
  taskId: number
  startTime: string
  endTime: string
}

export type ShareError = 'too_few_minutes' | 'mismatch'

const MINUTES_PER_HOUR = 60
/** 23:59, the server's own limit: a slot ending at 24:00 could not be saved again from the edit form. */
const LAST_MINUTE_OF_DAY = 24 * MINUTES_PER_HOUR - 1

function formatClock(totalMinutes: number): string {
  const hours = String(Math.floor(totalMinutes / MINUTES_PER_HOUR)).padStart(2, '0')
  const minutes = String(totalMinutes % MINUTES_PER_HOUR).padStart(2, '0')
  return `${hours}:${minutes}`
}

/**
 * Even split: floor(total / N) each, the remainder (1 minute each) to the first
 * tasks in order. A total below N cannot give everyone >= 1 minute: the error
 * is reported and every quota is left at 0 so the rows stay editable.
 */
export function splitMinutesEvenly(
  total: number,
  taskIds: number[],
): { shares: TimeEntryShare[]; error: 'too_few_minutes' | null } {
  const count = taskIds.length
  if (count === 0 || total < count) {
    return { shares: taskIds.map((taskId) => ({ task_id: taskId, minutes: 0 })), error: count === 0 ? null : 'too_few_minutes' }
  }
  const base = Math.floor(total / count)
  const remainder = total % count
  return {
    shares: taskIds.map((taskId, index) => ({ task_id: taskId, minutes: base + (index < remainder ? 1 : 0) })),
    error: null,
  }
}

/** Consecutive slots from `startTime` (`HH:mm`); `null` without a valid start. Slots never run past 23:59. */
export function shareSlots(startTime: string | null, shares: TimeEntryShare[]): ShareSlot[] | null {
  const start = startTime ? timeValueToMinutes(startTime) : null
  if (start === null) {
    return null
  }
  let cursor = start
  return shares.map((share) => {
    const slotStart = cursor
    cursor = Math.min(LAST_MINUTE_OF_DAY, cursor + Math.max(0, share.minutes))
    return { taskId: share.task_id, startTime: formatClock(slotStart), endTime: formatClock(cursor) }
  })
}

export function sumShares(shares: TimeEntryShare[]): number {
  return shares.reduce((sum, share) => sum + share.minutes, 0)
}

/** Mirrors the server rules: every quota >= 1 minute, quotas add up to the total. */
export function validateShares(total: number, shares: TimeEntryShare[]): ShareError | null {
  if (shares.some((share) => !Number.isInteger(share.minutes) || share.minutes < 1)) {
    return 'too_few_minutes'
  }
  return sumShares(shares) === total ? null : 'mismatch'
}
