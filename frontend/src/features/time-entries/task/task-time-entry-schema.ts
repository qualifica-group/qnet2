/**
 * RHF + Zod schema of the Task-embedded "Nuovo intervallo" editor (spec 0122
 * MT-F6, D-9). Structurally identical to `TimeEntryFormValues` (`form/`) so
 * the values stay assignment-compatible with the shared `TimeEntryEditor`,
 * but WITHOUT the title requirement: the task-scoped endpoint
 * (`POST /api/tasks/{task}/time-entries`) never accepts title/links — the
 * server derives both from the task. A dedicated schema instead of reusing
 * `buildTimeEntrySchema` (which requires `title` when `task_id` is null,
 * always the case here since the task is never a form field).
 */

import { z } from 'zod'
import type { TFunction } from 'i18next'
import { timeValueToMinutes } from '@/features/time-entries/time-entry-format'

/** `notes` column limit (data_contract), mirrors `form/time-entry-schema.ts`. */
const NOTES_MAX_LENGTH = 5000
/** `minutes` bounds (D-6/AC-003): 1..1440. */
const MIN_MINUTES = 1
const MAX_MINUTES = 1440

interface RefinedValues {
  date: string
  task_type_id: number | null
  start_time: string | null
  end_time: string | null
  minutes: number | null
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

function addMinutesIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if (values.minutes === null) {
    ctx.addIssue({ code: 'custom', path: ['minutes'], message: t('timeEntries.form.minutesRequired') })
    return
  }
  if (values.minutes < MIN_MINUTES || values.minutes > MAX_MINUTES) {
    ctx.addIssue({ code: 'custom', path: ['minutes'], message: t('timeEntries.form.minutesInvalid') })
  }
}

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

/**
 * Builds the task editor's schema. `title`/`registry_id`/`opportunity_id`/
 * `work_order_id`/`task_id` stay unconstrained, unused fields: `TimeEntryEditor`
 * never renders them here (`showTitle`/`showContext` both false), they exist
 * only so `form.control` structurally satisfies the shared component's props.
 * `work_order_stage_id` is deliberately OMITTED (unlike the others): it is
 * `?`-optional on `TimeEntryFormValues` precisely so a shadow schema may leave
 * it out entirely and stay assignment-compatible — this one has no writable
 * caller of its own (the task-scoped create endpoint has no such field either).
 */
export function buildTaskTimeEntrySchema(t: TFunction) {
  return z
    .object({
      title: z.string(),
      date: z.string(),
      task_type_id: z.number().nullable(),
      start_time: z.string().nullable(),
      end_time: z.string().nullable(),
      minutes: z.number().nullable(),
      notes: z.string().max(NOTES_MAX_LENGTH).nullable(),
      registry_id: z.number().nullable(),
      opportunity_id: z.number().nullable(),
      work_order_id: z.number().nullable(),
      task_id: z.number().nullable(),
    })
    .superRefine((values, ctx) => {
      addDateIssue(values, ctx, t)
      addTaskTypeIssue(values, ctx, t)
      addMinutesIssue(values, ctx, t)
      addTimeRangeIssue(values, ctx, t)
    })
}

export type TaskTimeEntryFormValues = z.infer<ReturnType<typeof buildTaskTimeEntrySchema>>
