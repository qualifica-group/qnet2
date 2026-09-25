import type { z } from 'zod'
import type { TFunction } from 'i18next'
import type {
  TaskRecurrenceEndMode,
  TaskRecurrenceFrequency,
  TaskRecurrenceMonthMode,
} from '@/features/tasks/types'

/** Narrower mirror of the `recurrence` object, just what `addRecurrenceIssues` reads. */
export interface RefinedRecurrenceValues {
  enabled: boolean
  frequency: TaskRecurrenceFrequency | null
  interval: number | null
  weekdays: number[]
  month_mode: TaskRecurrenceMonthMode | null
  month_day: number | null
  ordinal: number | null
  ordinal_weekday: number | null
  year_month: number | null
  ends: TaskRecurrenceEndMode | null
  ends_on: string | null
  occurrence_count: number | null
}

/** Narrower mirror of the form values `addRecurrenceIssues` needs, beyond the `recurrence` object itself. */
interface RecurrenceRefineValues {
  end_date: string | null
  recurrence: RefinedRecurrenceValues
}

/**
 * Spec 0120 D-1/AC-033: the write path's conditional rules, replicated so the
 * user sees the missing field inline instead of waiting for the server's
 * 422. Every check is SKIPPED while `enabled` is false — a disabled section
 * carries no rule to validate, and `end_date` itself is already covered
 * unconditionally by `addMissingEndDateIssue` in `task-schema.ts` (D-1: a
 * recurrence with no scadenza is not calculable, but the form already
 * requires one either way).
 */
export function addRecurrenceIssues(
  values: RecurrenceRefineValues,
  ctx: z.RefinementCtx,
  t: TFunction,
): void {
  const { recurrence } = values
  if (!recurrence.enabled) {
    return
  }

  if (recurrence.interval === null || recurrence.interval < 1) {
    ctx.addIssue({
      code: 'custom',
      path: ['recurrence', 'interval'],
      message: t('tasks.form.recurrence.intervalInvalid'),
    })
  }

  if (recurrence.frequency === 'weekly' && recurrence.weekdays.length === 0) {
    ctx.addIssue({
      code: 'custom',
      path: ['recurrence', 'weekdays'],
      message: t('tasks.form.recurrence.weekdaysRequired'),
    })
  }

  // Spec 0155 D-1: monthly/yearly share the same fixed/ordinal day-of-month
  // discriminator. A `month_mode` not yet picked defaults to `fixed` here —
  // the section seeds it explicitly the moment the user picks either
  // frequency, so this fallback only ever matters for a rule built without
  // going through that picker (e.g. a fixture).
  if (recurrence.frequency === 'monthly' || recurrence.frequency === 'yearly') {
    const monthMode = recurrence.month_mode ?? 'fixed'
    if (monthMode === 'fixed') {
      if (recurrence.month_day === null || recurrence.month_day < 1 || recurrence.month_day > 31) {
        ctx.addIssue({
          code: 'custom',
          path: ['recurrence', 'month_day'],
          message: t('tasks.form.recurrence.monthDayInvalid'),
        })
      }
    } else {
      if (recurrence.ordinal === null || recurrence.ordinal < 1 || recurrence.ordinal > 5) {
        ctx.addIssue({
          code: 'custom',
          path: ['recurrence', 'ordinal'],
          message: t('tasks.form.recurrence.ordinalInvalid'),
        })
      }
      if (
        recurrence.ordinal_weekday === null ||
        recurrence.ordinal_weekday < 1 ||
        recurrence.ordinal_weekday > 7
      ) {
        ctx.addIssue({
          code: 'custom',
          path: ['recurrence', 'ordinal_weekday'],
          message: t('tasks.form.recurrence.ordinalWeekdayInvalid'),
        })
      }
    }
  }

  if (
    recurrence.frequency === 'yearly' &&
    (recurrence.year_month === null || recurrence.year_month < 1 || recurrence.year_month > 12)
  ) {
    ctx.addIssue({
      code: 'custom',
      path: ['recurrence', 'year_month'],
      message: t('tasks.form.recurrence.yearMonthInvalid'),
    })
  }

  if (recurrence.ends === 'on_date') {
    if (!recurrence.ends_on) {
      ctx.addIssue({
        code: 'custom',
        path: ['recurrence', 'ends_on'],
        message: t('tasks.form.recurrence.endsOnRequired'),
      })
    } else if (values.end_date && recurrence.ends_on <= values.end_date) {
      ctx.addIssue({
        code: 'custom',
        path: ['recurrence', 'ends_on'],
        message: t('tasks.form.recurrence.endsOnAfterEndDate'),
      })
    }
  }

  if (
    recurrence.ends === 'after_count' &&
    (recurrence.occurrence_count === null || recurrence.occurrence_count < 1)
  ) {
    ctx.addIssue({
      code: 'custom',
      path: ['recurrence', 'occurrence_count'],
      message: t('tasks.form.recurrence.occurrenceCountInvalid'),
    })
  }
}
