import type { TFunction } from 'i18next'
import { formatDate } from '@/lib/formatting/date-display'
import type { TaskRecurrenceDetail } from '@/features/tasks/types'

/** ISO-8601 order (Monday=1..Sunday=7), shared with `task-recurrence-weekdays-field.tsx`. */
const WEEKDAY_ORDER = [1, 2, 3, 4, 5, 6, 7] as const
const WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const

/** The picked weekdays' own sentence phrase ("il lunedì"/"Monday"), ISO-ascending. */
function weekdayPhrases(weekdays: number[], t: TFunction): string[] {
  return WEEKDAY_ORDER.filter((day) => weekdays.includes(day)).map((day) =>
    t(`tasks.detail.recurrence.weekday.${WEEKDAY_KEYS[day - 1]}`),
  )
}

/** Joins weekday phrases the way the active locale lists things ("A e B" / "A and B"). */
function joinWeekdays(phrases: string[], language: string): string {
  if (typeof Intl.ListFormat === 'function') {
    return new Intl.ListFormat(language, { style: 'long', type: 'conjunction' }).format(phrases)
  }
  return phrases.join(', ')
}

function frequencyPhrase(rule: TaskRecurrenceDetail, t: TFunction, language: string): string {
  if (rule.frequency === 'daily') {
    return t('tasks.detail.recurrence.daily', { count: rule.interval })
  }
  if (rule.frequency === 'weekly') {
    const weekdays = joinWeekdays(weekdayPhrases(rule.weekdays ?? [], t), language)
    return t('tasks.detail.recurrence.weekly', { count: rule.interval, weekdays })
  }
  return t('tasks.detail.recurrence.monthly', { count: rule.interval, day: rule.month_day })
}

function endsPhrase(rule: TaskRecurrenceDetail, t: TFunction): string {
  if (rule.ends === 'on_date') {
    return t('tasks.detail.recurrence.endsOnDate', { date: formatDate(rule.ends_on) })
  }
  if (rule.ends === 'after_count') {
    return t('tasks.detail.recurrence.endsAfterCount', { count: rule.occurrence_count ?? 0 })
  }
  return ''
}

/**
 * Renders a `TaskRecurrenceDetail` as one human-readable sentence for the
 * detail badge (spec 0120 AC-035), e.g. "Ogni 2 settimane il lunedì e il
 * mercoledì, fino al 31/03/2027". Pure: only `t`/`formatDate`, no React.
 */
export function formatTaskRecurrenceRule(
  rule: TaskRecurrenceDetail,
  t: TFunction,
  language: string,
): string {
  return `${frequencyPhrase(rule, t, language)}${endsPhrase(rule, t)}`
}
