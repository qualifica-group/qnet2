import type { TFunction } from 'i18next'
import { formatDate } from '@/lib/formatting/date-display'
import { WEEKDAY_KEYS, WEEKDAY_ORDER } from '@/features/tasks/task-recurrence-weekdays'
import type { TaskRecurrenceDetail } from '@/features/tasks/types'

/** The picked weekdays' own sentence phrase ("il lunedì"/"Monday"), ISO-ascending. */
function weekdayPhrases(weekdays: number[], t: TFunction): string[] {
  return WEEKDAY_ORDER.filter((day) => weekdays.includes(day)).map((day) =>
    t(`tasks.detail.recurrence.weekday.${WEEKDAY_KEYS[day - 1]}`),
  )
}

/** The bare weekday name ("martedì"/"Tuesday"), no leading article — spec 0155 D-1 ordinal phrases own it. */
function weekdayName(weekday: number, t: TFunction): string {
  return t(`tasks.detail.recurrence.weekdayName.${WEEKDAY_KEYS[weekday - 1]}`)
}

/**
 * Locale-aware full month name ("marzo"/"March"), never a hard-coded
 * English/Italian table. Exported: `task-recurrence-section.tsx` reuses it
 * for the "Mese" picker's own option labels (single source of truth).
 */
export function monthName(month: number, language: string): string {
  return new Intl.DateTimeFormat(language, { month: 'long' }).format(new Date(Date.UTC(2000, month - 1, 1)))
}

/**
 * English ordinal suffix per `Intl.PluralRules` category ("1st"/"2nd"/
 * "3rd"/"4th"...). Italian has no per-number ordinal suffix at all (just the
 * "°" mark), so it never consults this table.
 */
const ORDINAL_SUFFIXES: Record<string, string> = { one: 'st', two: 'nd', few: 'rd', other: 'th' }

/**
 * The ordinal label ("2°" in Italian, "2nd" in English) via `Intl.PluralRules`
 * directly — deliberately NOT an i18next resource key: the ordinal plural
 * CATEGORIES themselves differ by language (English needs one/two/few/other,
 * Italian only "other"), which the generated `TranslationResources` type
 * (derived once from `en.ts`) cannot express per-locale. Exported for the
 * same reason as `monthName` above.
 */
export function ordinalLabel(ordinal: number, language: string): string {
  if (!language.startsWith('en')) {
    return `${ordinal}°`
  }
  const category = new Intl.PluralRules('en', { type: 'ordinal' }).select(ordinal)
  return `${ordinal}${ORDINAL_SUFFIXES[category] ?? 'th'}`
}

/** Joins weekday phrases the way the active locale lists things ("A e B" / "A and B"). */
function joinWeekdays(phrases: string[], language: string): string {
  if (typeof Intl.ListFormat === 'function') {
    return new Intl.ListFormat(language, { style: 'long', type: 'conjunction' }).format(phrases)
  }
  return phrases.join(', ')
}

/**
 * Spec 0155 D-1: a monthly/yearly rule's own day-of-month phrase, fixed
 * calendar day or ordinal weekday. The interpolation key is `nth`, NOT
 * `ordinal` — i18next reserves `ordinal` as the option that switches a key
 * to its `_ordinal_*` plural forms (see `ordinalLabel`'s own doc comment),
 * so passing our value under that name would silently break the `_one`/
 * `_other` (cardinal) pluralization these keys actually use.
 */
function monthlyPhrase(rule: TaskRecurrenceDetail, t: TFunction, language: string): string {
  if (rule.month_mode === 'ordinal' && rule.ordinal !== null && rule.ordinal_weekday !== null) {
    return t('tasks.detail.recurrence.monthlyOrdinal', {
      count: rule.interval,
      nth: ordinalLabel(rule.ordinal, language),
      weekday: weekdayName(rule.ordinal_weekday, t),
    })
  }
  return t('tasks.detail.recurrence.monthly', { count: rule.interval, day: rule.month_day })
}

/** Spec 0155 D-1: a yearly rule's own phrase, e.g. "Ogni anno il 2° martedì di marzo". */
function yearlyPhrase(rule: TaskRecurrenceDetail, t: TFunction, language: string): string {
  const month = rule.year_month !== null ? monthName(rule.year_month, language) : ''
  if (rule.month_mode === 'ordinal' && rule.ordinal !== null && rule.ordinal_weekday !== null) {
    return t('tasks.detail.recurrence.yearlyOrdinal', {
      count: rule.interval,
      nth: ordinalLabel(rule.ordinal, language),
      weekday: weekdayName(rule.ordinal_weekday, t),
      month,
    })
  }
  return t('tasks.detail.recurrence.yearlyFixed', { count: rule.interval, day: rule.month_day, month })
}

function frequencyPhrase(rule: TaskRecurrenceDetail, t: TFunction, language: string): string {
  // Spec 0155 D-1: `custom` is q-net's "every N days" — the exact same
  // effect and phrase as `daily` with its own interval.
  if (rule.frequency === 'daily' || rule.frequency === 'custom') {
    return t('tasks.detail.recurrence.daily', { count: rule.interval })
  }
  if (rule.frequency === 'weekly') {
    const weekdays = joinWeekdays(weekdayPhrases(rule.weekdays ?? [], t), language)
    return t('tasks.detail.recurrence.weekly', { count: rule.interval, weekdays })
  }
  if (rule.frequency === 'monthly') {
    return monthlyPhrase(rule, t, language)
  }
  return yearlyPhrase(rule, t, language)
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
 * detail badge (spec 0120 AC-035, spec 0155 D-1), e.g. "Ogni 2 settimane il
 * lunedì e il mercoledì, fino al 31/03/2027" or "Ogni anno il 2° martedì di
 * marzo". Pure: only `t`/`formatDate`/`Intl`, no React.
 */
export function formatTaskRecurrenceRule(
  rule: TaskRecurrenceDetail,
  t: TFunction,
  language: string,
): string {
  const workdaysSuffix = rule.workdays_only ? t('tasks.detail.recurrence.workdaysOnlySuffix') : ''
  return `${frequencyPhrase(rule, t, language)}${workdaysSuffix}${endsPhrase(rule, t)}`
}
