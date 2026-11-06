import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { TaskRecurrenceRule } from '@/features/tasks/task-recurrence-format'
import { TASK_RECURRENCE_FREQUENCIES, TASK_RECURRENCE_MONTH_MODES } from '@/features/tasks/task-recurrence-types'
import type {
  OutboundEmailRecurrence,
  OutboundEmailRecurrenceInput,
} from '@/features/outbound-emails/types'

const MONTH_DAY_MAX = 31

/**
 * "Ricorrenza" slice of the composer form (spec 0362). Lenient on purpose:
 * it is part of the RHF resolver, so an unfinished rule must not block
 * "Salva bozza". The strict check runs only on "Programma"
 * (`buildRecurrenceScheduleSchema`).
 */
export const recurrenceFormSchema = z.object({
  enabled: z.boolean(),
  frequency: z.enum(TASK_RECURRENCE_FREQUENCIES),
  interval: z.number().nullable(),
  weekdays: z.array(z.number()),
  month_mode: z.enum(TASK_RECURRENCE_MONTH_MODES).nullable(),
  month_day: z.number().nullable(),
  ordinal: z.number().nullable(),
  ordinal_weekday: z.number().nullable(),
  year_month: z.number().nullable(),
  workdays_only: z.boolean(),
  starts_on: z.string().nullable(),
  ends_on: z.string().nullable(),
})

export type RecurrenceFormValues = z.infer<typeof recurrenceFormSchema>

export const EMPTY_RECURRENCE_VALUES: RecurrenceFormValues = {
  enabled: false,
  frequency: 'weekly',
  interval: 1,
  weekdays: [],
  month_mode: null,
  month_day: null,
  ordinal: null,
  ordinal_weekday: null,
  year_month: null,
  workdays_only: false,
  starts_on: null,
  ends_on: null,
}

/** Local calendar day as `YYYY-MM-DD` (the server compares dates, not instants). */
export function todayIso(now: Date = new Date()): string {
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

/** Form state for a draft: a rule kept by a previous schedule comes back switched on. */
export function mapRecurrenceToFormValues(recurrence: OutboundEmailRecurrence | null): RecurrenceFormValues {
  if (!recurrence) {
    return EMPTY_RECURRENCE_VALUES
  }
  return {
    enabled: true,
    frequency: recurrence.frequency,
    interval: recurrence.interval,
    weekdays: recurrence.weekdays ?? [],
    month_mode: recurrence.month_mode,
    month_day: recurrence.month_day,
    ordinal: recurrence.ordinal,
    ordinal_weekday: recurrence.ordinal_weekday,
    year_month: recurrence.year_month,
    workdays_only: recurrence.workdays_only,
    starts_on: recurrence.starts_on,
    ends_on: recurrence.ends_on,
  }
}

type RuleFields = Omit<OutboundEmailRecurrenceInput, 'starts_on' | 'ends_on'>

function hasMonthDay(values: RecurrenceFormValues): boolean {
  if (values.month_mode === 'ordinal') {
    return values.ordinal !== null && values.ordinal_weekday !== null
  }
  return values.month_day !== null && values.month_day >= 1 && values.month_day <= MONTH_DAY_MAX
}

function monthFields(values: RecurrenceFormValues, yearly: boolean): Partial<RuleFields> | null {
  if (!hasMonthDay(values) || (yearly && values.year_month === null)) {
    return null
  }
  const dayFields =
    values.month_mode === 'ordinal'
      ? { month_mode: 'ordinal' as const, ordinal: values.ordinal ?? undefined, ordinal_weekday: values.ordinal_weekday ?? undefined }
      : { month_mode: 'fixed' as const, month_day: values.month_day ?? undefined }
  return yearly ? { ...dayFields, year_month: values.year_month ?? undefined } : dayFields
}

/**
 * The rule part of the payload, with ONLY the fields the picked frequency
 * uses (the server prohibits the others). `null` while the rule is missing
 * something it needs.
 */
function buildRuleFields(values: RecurrenceFormValues): RuleFields | null {
  const { frequency, interval, workdays_only } = values
  if (interval === null || !Number.isInteger(interval) || interval < 1) {
    return null
  }
  const base = { frequency, interval, workdays_only }
  if (frequency === 'weekly') {
    return values.weekdays.length > 0 ? { ...base, weekdays: [...values.weekdays].sort((a, b) => a - b) } : null
  }
  if (frequency === 'monthly' || frequency === 'yearly') {
    const month = monthFields(values, frequency === 'yearly')
    return month ? { ...base, ...month } : null
  }
  return base
}

/** Full `schedule` payload, or `null` while the rule or either date is incomplete. */
export function toRecurrenceInput(values: RecurrenceFormValues): OutboundEmailRecurrenceInput | null {
  const rule = buildRuleFields(values)
  if (!rule || !values.starts_on || !values.ends_on) {
    return null
  }
  return { ...rule, starts_on: values.starts_on, ends_on: values.ends_on }
}

/** The rule as `formatTaskRecurrenceRule` reads it (always ends on a date, spec 0362 D-4). */
function toSentenceRule(fields: RuleFields, endsOn: string): TaskRecurrenceRule {
  return {
    frequency: fields.frequency,
    interval: fields.interval,
    weekdays: fields.weekdays ?? null,
    month_mode: fields.month_mode ?? null,
    month_day: fields.month_day ?? null,
    ordinal: fields.ordinal ?? null,
    ordinal_weekday: fields.ordinal_weekday ?? null,
    year_month: fields.year_month ?? null,
    workdays_only: fields.workdays_only,
    ends: 'on_date',
    ends_on: endsOn,
    occurrence_count: null,
  }
}

/** Live sentence of the rule being drafted; `null` until rule and end date are complete. */
export function previewRule(values: RecurrenceFormValues): TaskRecurrenceRule | null {
  const fields = buildRuleFields(values)
  return fields && values.ends_on ? toSentenceRule(fields, values.ends_on) : null
}

/** Sentence rule of a persisted series (detail summary). */
export function recurrenceToRule(recurrence: OutboundEmailRecurrence): TaskRecurrenceRule {
  return { ...recurrence, ends: 'on_date', occurrence_count: null }
}

/** Strict check run on "Programma" only; issue paths are `recurrence.<field>` once nested in the composer schema. */
export function buildRecurrenceScheduleSchema(t: TFunction, today: string = todayIso()) {
  return recurrenceFormSchema.superRefine((values, ctx) => {
    const fail = (path: keyof RecurrenceFormValues, key: string) =>
      ctx.addIssue({ code: 'custom', path: [path], message: t(`outboundEmails.recurrence.errors.${key}`) })

    if (values.interval === null || !Number.isInteger(values.interval) || values.interval < 1) {
      fail('interval', 'intervalInvalid')
    }
    if (values.frequency === 'weekly' && values.weekdays.length === 0) {
      fail('weekdays', 'weekdaysRequired')
    }
    if (values.frequency === 'monthly' || values.frequency === 'yearly') {
      if (!hasMonthDay(values)) {
        fail(values.month_mode === 'ordinal' ? 'ordinal' : 'month_day', values.month_mode === 'ordinal' ? 'ordinalRequired' : 'monthDayInvalid')
      }
      if (values.frequency === 'yearly' && values.year_month === null) {
        fail('year_month', 'yearMonthRequired')
      }
    }
    if (!values.starts_on) {
      fail('starts_on', 'startsOnRequired')
    } else if (values.starts_on < today) {
      fail('starts_on', 'startsOnPast')
    }
    if (!values.ends_on) {
      fail('ends_on', 'endsOnRequired')
    } else if (values.starts_on && values.ends_on < values.starts_on) {
      fail('ends_on', 'endsOnBeforeStart')
    }
  })
}
