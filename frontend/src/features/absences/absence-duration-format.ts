import type { TFunction } from 'i18next'

const MINUTES_PER_HOUR = 60

/**
 * Minutes as days + hours of the person's working day (`600` with `480` per day -> "1 g 2 h").
 * Without a positive daily amount it falls back to hours and minutes only. Negative values keep the sign.
 */
export function formatAbsenceMinutes(minutes: number, dailyMinutes: number, t: TFunction): string {
  const total = Math.round(Math.abs(minutes))
  const days = dailyMinutes > 0 ? Math.floor(total / dailyMinutes) : 0
  const rest = dailyMinutes > 0 ? total - days * dailyMinutes : total
  const hours = Math.floor(rest / MINUTES_PER_HOUR)
  const mins = rest % MINUTES_PER_HOUR

  const parts: string[] = []
  if (days > 0) parts.push(t('absences.duration.days', { value: days }))
  if (hours > 0) parts.push(t('absences.duration.hours', { value: hours }))
  if (mins > 0) parts.push(t('absences.duration.minutes', { value: mins }))
  if (parts.length === 0) return t('absences.duration.hours', { value: 0 })

  return `${minutes < 0 ? '-' : ''}${parts.join(' ')}`
}
