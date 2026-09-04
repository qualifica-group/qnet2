import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { TaskStatusSystemKey } from '@/features/tasks/types'

/** Backend `title` column limit (`string(191)`). */
const TITLE_MAX_LENGTH = 191
/** `estimated_minutes` is `unsignedInteger`: minutes, never a "2h30" string (D-11). */
const MIN_ESTIMATED_MINUTES = 0

/**
 * The two system keys that mark a closure (D-5/D-7). The ONLY thing the
 * closure-feedback rule may branch on: a status label never enters a
 * condition (AC-024). A custom status (`system_key === null`) is never a
 * closing one, which is the declared consequence of D-5.
 */
export const CLOSING_STATUS_SYSTEM_KEYS: readonly TaskStatusSystemKey[] = [
  'closed_positive',
  'closed_negative',
]

/** Whether the given system key closes the task (D-7). */
export function isClosingStatus(systemKey: TaskStatusSystemKey | null | undefined): boolean {
  return systemKey !== null && systemKey !== undefined && CLOSING_STATUS_SYSTEM_KEYS.includes(systemKey)
}

/**
 * Shared field shape. `completion_percentage` and `creator_id` are DELIBERATELY
 * absent: they are `prohibited` server-side (D-6/D-10), so keeping them out of
 * the schema makes it structurally impossible for the form to ever send them
 * (AC-084).
 */
function baseFields(t: TFunction) {
  return {
    title: z
      .string()
      .min(1, t('tasks.form.titleRequired'))
      .max(TITLE_MAX_LENGTH, t('tasks.form.titleMax')),
    task_status_id: z.number().nullable(),
    description: z.string().nullable(),
    registry_id: z.number().nullable(),
    referent_id: z.number().nullable(),
    parent_task_id: z.number().nullable(),
    task_type_id: z.number().nullable(),
    task_priority_id: z.number().nullable(),
    task_importance_id: z.number().nullable(),
    task_category_id: z.number().nullable(),
    opportunity_id: z.number().nullable(),
    work_order_id: z.number().nullable(),
    requester_id: z.number().nullable(),
    start_date: z.string().nullable(),
    end_date: z.string().nullable(),
    completion_date: z.string().nullable(),
    start_time: z.string().nullable(),
    end_time: z.string().nullable(),
    estimated_minutes: z
      .number()
      .int(t('tasks.form.estimatedMinutesInvalid'))
      .min(MIN_ESTIMATED_MINUTES, t('tasks.form.estimatedMinutesInvalid'))
      .nullable(),
    is_blocked: z.boolean(),
    requires_closure_feedback: z.boolean(),
    closure_feedback: z.string().nullable(),
    // Flat id arrays (AC-083); the same user may sit in both.
    assignee_ids: z.array(z.number()),
    watcher_ids: z.array(z.number()),
  }
}

/** Values the two refinements below read; narrower than the whole form. */
interface RefinedValues {
  task_status_id: number | null
  requires_closure_feedback: boolean
  closure_feedback: string | null
}

/** `task_status_id` is NOT NULL server-side, so an unpicked status is a client error. */
function addMissingStatusIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if (values.task_status_id === null) {
    ctx.addIssue({
      code: 'custom',
      path: ['task_status_id'],
      message: t('tasks.form.statusRequired'),
    })
  }
}

/**
 * D-7 replicated for UX: with the flag on AND a closing status picked, the
 * feedback must be a non-empty string after trim. This MIRRORS the server
 * rule (`TaskClosureFeedbackGuard`), it does not replace it — the 422 path
 * stays wired in `useTaskForm`.
 */
function addMissingClosureFeedbackIssue(
  values: RefinedValues,
  systemKey: TaskStatusSystemKey | null,
  ctx: z.RefinementCtx,
  t: TFunction,
): void {
  if (!values.requires_closure_feedback || !isClosingStatus(systemKey)) {
    return
  }
  if ((values.closure_feedback ?? '').trim() === '') {
    ctx.addIssue({
      code: 'custom',
      path: ['closure_feedback'],
      message: t('tasks.form.closureFeedbackRequired'),
    })
  }
}

/**
 * Builds the task form schema. `statusSystemKey` is the `system_key` of the
 * status currently picked, read off the picker's own `meta` (see
 * `taskStatusMetaOf`) rather than off the form values: it is not a writable
 * field, so it never becomes part of the payload. One schema for create and
 * edit — the partial PATCH diff is computed by the payload builder, not by a
 * second shape that could drift.
 */
export function buildTaskSchema(t: TFunction, statusSystemKey: TaskStatusSystemKey | null = null) {
  return z.object(baseFields(t)).superRefine((values, ctx) => {
    addMissingStatusIssue(values, ctx, t)
    addMissingClosureFeedbackIssue(values, statusSystemKey, ctx, t)
  })
}

export type TaskFormValues = z.infer<ReturnType<typeof buildTaskSchema>>
