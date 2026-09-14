/**
 * Pure formatting/parsing helpers for a time entry's duration (spec 0122
 * AC-029). No React, no i18n — the "Tempo" field of the create/edit form (F2)
 * and every minutes display (day card, KPI tiles, exports preview) go
 * through these so the hh:mm/HH:MM conventions stay in one place.
 */

/** Matches a strict `HH:MM` value, hours 00-23, minutes 00-59 (D-6: no midnight crossing). */
const TIME_VALUE_PATTERN = /^([01]\d|2[0-3]):([0-5]\d)$/

/** Highest minute-of-day value a `HH:MM` field can hold (23:59). */
export const MAX_TIME_VALUE_MINUTES = 23 * 60 + 59

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

/**
 * Formats a minute count as a compact duration label: `45m` under an hour,
 * `7h 30m` / `8h 00m` otherwise (minutes always zero-padded once hours show).
 * Negative/NaN input clamps to 0 so a corrupt value never renders garbage.
 */
export function formatMinutesLabel(totalMinutes: number): string {
  const safeMinutes = Number.isFinite(totalMinutes) ? Math.max(0, Math.round(totalMinutes)) : 0
  const hours = Math.floor(safeMinutes / 60)
  const minutes = safeMinutes % 60

  return hours <= 0 ? `${minutes}m` : `${hours}h ${pad(minutes)}m`
}

/**
 * Renders a minute count as the `HH:MM` value the "Tempo" field displays.
 * Clamped to `MAX_TIME_VALUE_MINUTES` (the field never shows 24:00+).
 */
export function minutesToTimeValue(totalMinutes: number): string {
  const safeMinutes = Number.isFinite(totalMinutes)
    ? Math.min(MAX_TIME_VALUE_MINUTES, Math.max(0, Math.round(totalMinutes)))
    : 0

  return `${pad(Math.floor(safeMinutes / 60))}:${pad(safeMinutes % 60)}`
}

/** Parses a `HH:MM` field value into minutes, or `null` when it is not a valid time-of-day. */
export function timeValueToMinutes(value: string): number | null {
  const match = TIME_VALUE_PATTERN.exec(value.trim())
  if (!match) {
    return null
  }

  return Number(match[1]) * 60 + Number(match[2])
}

/**
 * Computes the tracked minutes from a start/end pair (D-6): `null` when
 * either side is missing, invalid, or `end <= start` — the form then leaves
 * whatever the user already typed in "Tempo" untouched instead of zeroing it.
 */
export function computeTrackedMinutes(
  startTime: string | null | undefined,
  endTime: string | null | undefined,
): number | null {
  if (!startTime || !endTime) {
    return null
  }

  const startMinutes = timeValueToMinutes(startTime)
  const endMinutes = timeValueToMinutes(endTime)
  if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
    return null
  }

  return endMinutes - startMinutes
}
