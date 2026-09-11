import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'

/** Backend `title` column limit (`string(191)`). */
const TITLE_MAX_LENGTH = 191
/** `estimated_minutes` is `unsignedInteger`: minutes, never a "2h30" string (D-11). */
const MIN_ESTIMATED_MINUTES = 0

/**
 * The two PHASES that mark a closure (D-7). The ONLY thing the
 * closure-feedback rule may branch on: a status label never enters a condition
 * (AC-024), and neither does `system_key` any more.
 *
 * Rectification of 2026-09-04: closing used to be decided by the system key,
 * so an ordinary row "was never a closing one" — that consequence of D-5 no
 * longer holds. Every row carries a phase, system or custom alike, so a status
 * an admin created and put in a closing phase closes the task exactly like a
 * seeded one, and the server 422s on the missing feedback either way.
 */
export const CLOSING_STATUS_GROUPS: readonly TaskStatusGroupValue[] = [
  'closed_positive',
  'closed_negative',
]

/** Whether the given phase closes the task (D-7). */
export function isClosingStatus(group: TaskStatusGroupValue | null | undefined): boolean {
  return group !== null && group !== undefined && CLOSING_STATUS_GROUPS.includes(group)
}

/**
 * Shared field shape. `completion_percentage`, `creator_id` and `is_blocked`
 * are DELIBERATELY absent: they are `prohibited` server-side (spec 0116 D-6,
 * D-10), so keeping them out of the schema makes it structurally impossible
 * for the form to ever send them (AC-084/AC-045). `is_blocked` is writable
 * ONLY by the `/block`/`/unblock` actions now.
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
    requires_closure_feedback: z.boolean(),
    closure_feedback: z.string().nullable(),
    // Flat id arrays; since D-9 `watcher_ids` may not overlap `requester_id`/
    // `assignee_ids` (see `addWatcherOverlapIssue`).
    assignee_ids: z.array(z.number()),
    watcher_ids: z.array(z.number()),
  }
}

/** Values the refinements below read; narrower than the whole form. */
interface RefinedValues {
  task_status_id: number | null
  requester_id: number | null
  end_date: string | null
  assignee_ids: number[]
  watcher_ids: number[]
  requires_closure_feedback: boolean
  closure_feedback: string | null
}

/** `task_status_id` is NOT NULL server-side; on PATCH it is still required (D-3/D-6: create derives it). */
function addMissingStatusIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if (values.task_status_id === null) {
    ctx.addIssue({
      code: 'custom',
      path: ['task_status_id'],
      message: t('tasks.form.statusRequired'),
    })
  }
}

/** `requester_id` is required on both create and PATCH-when-submitted (spec 0118 D-1/D-2). */
function addMissingRequesterIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if (values.requester_id === null) {
    ctx.addIssue({
      code: 'custom',
      path: ['requester_id'],
      message: t('tasks.form.requesterRequired'),
    })
  }
}

/** `assignee_ids` needs at least one element on both create and PATCH-when-submitted (D-1/D-2). */
function addMissingAssigneesIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if (values.assignee_ids.length === 0) {
    ctx.addIssue({
      code: 'custom',
      path: ['assignee_ids'],
      message: t('tasks.form.assigneesRequired'),
    })
  }
}

/** `end_date` is required on both create and PATCH-when-submitted (D-1/D-2). */
function addMissingEndDateIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  if ((values.end_date ?? '').trim() === '') {
    ctx.addIssue({
      code: 'custom',
      path: ['end_date'],
      message: t('tasks.form.endDateRequired'),
    })
  }
}

/**
 * D-9 replicated for UX: an id in `watcher_ids` may not also be the
 * requester or an assignee. This MIRRORS the server rule, it does not
 * replace it — the 422 path stays wired in `useTaskForm`. The CREATOR is
 * deliberately not checked here: it is not a form value (server-derived from
 * the actor), so this client mirror covers requester+assignees only; the
 * server 422 is the actual defense against a creator/watcher overlap.
 */
function addWatcherOverlapIssue(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  const reserved = new Set(values.assignee_ids)
  if (values.requester_id !== null) {
    reserved.add(values.requester_id)
  }
  if (values.watcher_ids.some((id) => reserved.has(id))) {
    ctx.addIssue({
      code: 'custom',
      path: ['watcher_ids'],
      message: t('tasks.form.watcherOverlap'),
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
  statusGroup: TaskStatusGroupValue | null,
  ctx: z.RefinementCtx,
  t: TFunction,
): void {
  if (!values.requires_closure_feedback || !isClosingStatus(statusGroup)) {
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
 * Builds the task form schema. `statusGroup` is the `group` of the status
 * currently picked, read off the picker's own `meta` (see `taskStatusMetaOf`)
 * rather than off the form values: it is not a writable field, so it never
 * becomes part of the payload. One schema for create and edit — the partial
 * PATCH diff is computed by the payload builder, not by a second shape that
 * could drift.
 *
 * `isCreate` (spec 0118 D-1/D-3) is the only thing that branches by mode:
 * `task_status_id` is required in edit only (the server derives it on
 * create, D-3/D-4), while `requester_id`/`assignee_ids`/`end_date` and the
 * watcher-overlap rule (D-9) apply in BOTH modes — a PATCH that submits them
 * is bound by the same requiredness as a POST (D-2).
 */
export function buildTaskSchema(
  t: TFunction,
  statusGroup: TaskStatusGroupValue | null = null,
  isCreate: boolean = false,
) {
  return z.object(baseFields(t)).superRefine((values, ctx) => {
    if (!isCreate) {
      addMissingStatusIssue(values, ctx, t)
    }
    addMissingRequesterIssue(values, ctx, t)
    addMissingAssigneesIssue(values, ctx, t)
    addMissingEndDateIssue(values, ctx, t)
    addWatcherOverlapIssue(values, ctx, t)
    addMissingClosureFeedbackIssue(values, statusGroup, ctx, t)
  })
}

export type TaskFormValues = z.infer<ReturnType<typeof buildTaskSchema>>
