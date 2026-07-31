/**
 * The two halves of a `YYYY-MM-DDTHH:mm` wire instant, for controls that edit
 * the date and the time separately because the TIME IS OPTIONAL (user
 * directive 2026-07-31). The wire value itself never changes shape: an instant
 * planned without an hour simply carries midnight, so nothing downstream
 * (payload diff, backend validation, sorting, filtering) has to know.
 *
 * Consequence, and the reason both directions live here: `T00:00` IS what "no
 * time set" looks like, so it must split back to a BLANK time — otherwise the
 * round trip would show an hour nobody typed. Display follows the same rule in
 * `formatDateTimeOptionalTime` (`features/table/cell-renderers.tsx`).
 */

/** The wire time of an instant whose optional time was never set. */
export const MIDNIGHT = '00:00'

/** Splits a wire instant into its two inputs; an instant at midnight has a blank time. */
export function splitInstant(value: string | null | undefined): { date: string; time: string } {
  const [date = '', rawTime = ''] = (value ?? '').split('T')
  // A `time` input rejects a value carrying seconds: keep `HH:mm` only.
  const time = rawTime.slice(0, MIDNIGHT.length)

  return { date, time: time === MIDNIGHT ? '' : time }
}

/** Recomposes the wire instant; no date means no instant at all, whatever the time says. */
export function joinInstant(date: string, time: string): string | null {
  return date === '' ? null : `${date}T${time === '' ? MIDNIGHT : time}`
}
