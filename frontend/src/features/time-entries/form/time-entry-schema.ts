/**
 * RHF + Zod schema of the time entry create/edit form (spec 0122 MT-F2,
 * D-6, AC-029/AC-030). One schema for create and edit: the wire payload
 * differs only in `user_id` (create-only, added by the caller, never a form
 * field), so no second shape is needed.
 */

import { z } from 'zod'
import type { TFunction } from 'i18next'
import { timeValueToMinutes } from '@/features/time-entries/time-entry-format'

/** Backend `title` column limit (`string(191)`, data_contract). */
const TITLE_MAX_LENGTH = 191
/** `notes` column limit (data_contract). */
const NOTES_MAX_LENGTH = 5000
/** `minutes` bounds (D-6/AC-003): 1..1440. */
const MIN_MINUTES = 1
const MAX_MINUTES = 1440

/**
 * Shared field shape. `title`/`registry_id`/`opportunity_id`/`work_order_id`
 * stay plain, unconstrained fields here: whether `title` is required and
 * whether the three links are locked is D-5's "task selected" branch, decided
 * in `superRefine` below (title) and in the editor's rendering (the links,
 * which the server ignores and overwrites once `task_id` is set, so no
 * client rule is needed for them).
 */
function baseFields() {
  return {
    title: z.string().max(TITLE_MAX_LENGTH),
    date: z.string(),
    task_type_id: z.number().nullable(),
    /** `HH:MM` or `null` — native `type="time"` inputs never emit a partial value. */
    start_time: z.string().nullable(),
    end_time: z.string().nullable(),
    minutes: z.number().nullable(),
    notes: z.string().max(NOTES_MAX_LENGTH).nullable(),
    registry_id: z.number().nullable(),
    opportunity_id: z.number().nullable(),
    work_order_id: z.number().nullable(),
    task_id: z.number().nullable(),
    /** `prohibited` without `work_order_id`, ignored with `task_id` (spec 0163 D-1) — server-checked, no client rule needed. */
    work_order_stage_id: z.number().nullable(),
  }
}

/** Values the refinements below read. */
interface RefinedValues {
  title: string
  date: string
  task_type_id: number | null
  start_time: string | null
  end_time: string | null
  minutes: number | null
  task_id: number | null
}

/** `title` is `required_without:task_id` (D-5): with a task picked, the server derives it. */
function addTitleIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if (values.task_id === null && values.title.trim() === '') {
    ctx.addIssue({ code: 'custom', path: ['title'], message: t('timeEntries.form.titleRequired') })
  }
}

function addDateIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if (values.date.trim() === '') {
    ctx.addIssue({ code: 'custom', path: ['date'], message: t('timeEntries.form.dateRequired') })
  }
}

function addTaskTypeIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if (values.task_type_id === null) {
    ctx.addIssue({ code: 'custom', path: ['task_type_id'], message: t('timeEntries.form.typeRequired') })
  }
}

/** `minutes` required, 1..1440 (D-6/AC-003). */
function addMinutesIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if (values.minutes === null) {
    ctx.addIssue({ code: 'custom', path: ['minutes'], message: t('timeEntries.form.minutesRequired') })
    return
  }
  if (values.minutes < MIN_MINUTES || values.minutes > MAX_MINUTES) {
    ctx.addIssue({ code: 'custom', path: ['minutes'], message: t('timeEntries.form.minutesInvalid') })
  }
}

/**
 * `end_time > start_time` (D-6/AC-002). The "both or neither" half of D-6 is
 * left to the server's own 422 (AC-030 does not exercise it, and the two time
 * fields already share one native `type="time"` UX, so a mid-edit mismatch is
 * transient rather than a state the user can submit from the editor).
 */
function addTimeRangeIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if (!values.start_time || !values.end_time) {
    return
  }
  const start = timeValueToMinutes(values.start_time)
  const end = timeValueToMinutes(values.end_time)
  if (start !== null && end !== null && end <= start) {
    ctx.addIssue({ code: 'custom', path: ['end_time'], message: t('timeEntries.form.endTimeBeforeStart') })
  }
}

/** Builds the time entry form schema, translated with the caller's `t`. */
export function buildTimeEntrySchema(t: TFunction) {
  return z.object(baseFields()).superRefine((values, ctx) => {
    addTitleIssue(values, ctx, t)
    addDateIssue(values, ctx, t)
    addTaskTypeIssue(values, ctx, t)
    addMinutesIssue(values, ctx, t)
    addTimeRangeIssue(values, ctx, t)
  })
}

export type TimeEntryFormValues = z.infer<ReturnType<typeof buildTimeEntrySchema>>
