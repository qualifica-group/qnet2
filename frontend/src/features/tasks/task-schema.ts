import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  TASK_RECURRENCE_END_MODES,
  TASK_RECURRENCE_FREQUENCIES,
  TASK_RECURRENCE_MONTH_MODES,
  type TaskRecurrenceEndMode,
  type TaskRecurrenceFrequency,
  type TaskRecurrenceMonthMode,
} from '@/features/tasks/types'
import { addParentDateRangeIssues, type ParentDateRange } from '@/features/tasks/task-parent-date-range'

export type { ParentDateRange } from '@/features/tasks/task-parent-date-range'

/** Backend `title` column limit (`string(191)`). */
const TITLE_MAX_LENGTH = 191
/** `estimated_minutes` is `unsignedInteger`: minutes, never a "2h30" string (D-11). */
const MIN_ESTIMATED_MINUTES = 0

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
    // Spec 0154 D-2/D-3: privacy flag and free rich text, sanitized like
    // `description` server-side; no client-side format rule of their own.
    is_private: z.boolean(),
    evidence: z.string().nullable(),
    registry_id: z.number().nullable(),
    referent_id: z.number().nullable(),
    parent_task_id: z.number().nullable(),
    // Spec 0154 D-8: required in BOTH modes now that the server precompiles a
    // default row on create — an omitted value here would never reach the
    // server as `null` (see `addMissingLookupIssue`).
    task_type_id: z.number().nullable(),
    task_priority_id: z.number().nullable(),
    task_importance_id: z.number().nullable(),
    task_category_id: z.number().nullable(),
    opportunity_id: z.number().nullable(),
    work_order_id: z.number().nullable(),
    // Spec 0154 D-4: must belong to `registry_id` when one is set (422
    // otherwise); cleared client-side on a registry change (`useTaskForm`).
    lead_id: z.number().nullable(),
    // Spec 0146 D-2/D-3: server-side requiredness (belongs to `work_order_id`,
    // prohibited on a sub-task) is not client-replicable without the fetched
    // fase list, so this stays a plain nullable id — `TaskLinksSection` hides
    // the control outright rather than duplicating the rule here.
    work_order_stage_id: z.number().nullable(),
    requester_id: z.number().nullable(),
    start_date: z.string().nullable(),
    end_date: z.string().nullable(),
    start_time: z.string().nullable(),
    end_time: z.string().nullable(),
    estimated_minutes: z
      .number()
      .int(t('tasks.form.estimatedMinutesInvalid'))
      .min(MIN_ESTIMATED_MINUTES, t('tasks.form.estimatedMinutesInvalid'))
      .nullable(),
    requires_closure_feedback: z.boolean(),
    // Spec 0121 D-1: sibling flag, independent of the one above (D-2 decides
    // the completion path from it, never a client choice). `closure_feedback`
    // itself is NOT a field here any more (spec 0121 D-7): it is written only
    // from the completion pop-up now, never from this form.
    requires_validation: z.boolean(),
    // Spec 0154 D-6: create-only UI toggle ("Crea gia' completato"); the
    // payload builder never sends it on a PATCH (`buildUpdatePayload`).
    is_completed: z.boolean(),
    // Spec 0154 D-7: ONE UI toggle behind both wire fields — the payload
    // builder maps it onto `notify_assigned_users` (create) or
    // `notify_new_assigned_users` (edit), never both. Not a wire field of its
    // own, hence the name does not match either.
    suppress_notifications: z.boolean(),
    // Flat id arrays; since D-9 `watcher_ids` may not overlap `requester_id`/
    // `assignee_ids` (see `addWatcherOverlapIssue`).
    assignee_ids: z.array(z.number()),
    watcher_ids: z.array(z.number()),
    // Spec 0120 D-1/D-12: `enabled` is a pure UI toggle, not a wire field — it
    // decides whether the payload builder sends the object below or `null`.
    // Every other member stays loosely typed (nullable/no min) at the base
    // level; `addRecurrenceIssues` is the ONLY place that enforces D-1's
    // conditional shape, and only while `enabled` is true.
    recurrence: z.object({
      enabled: z.boolean(),
      frequency: z.enum(TASK_RECURRENCE_FREQUENCIES).nullable(),
      interval: z.number().int().nullable(),
      weekdays: z.array(z.number()),
      // Spec 0155 D-1: monthly/yearly's own day-of-month discriminator, plus
      // the fixed/ordinal picks it gates.
      month_mode: z.enum(TASK_RECURRENCE_MONTH_MODES).nullable(),
      month_day: z.number().nullable(),
      ordinal: z.number().nullable(),
      ordinal_weekday: z.number().nullable(),
      // Spec 0155 D-1: yearly's own calendar month (1..12).
      year_month: z.number().nullable(),
      // Spec 0155 D-1: not frequency-conditional, a plain switch.
      workdays_only: z.boolean(),
      ends: z.enum(TASK_RECURRENCE_END_MODES).nullable(),
      ends_on: z.string().nullable(),
      occurrence_count: z.number().nullable(),
    }),
    // Spec 0155 D-3: create-only bulk sub-tasks, one level, up to 50 rows.
    // `assignee_ids` omitted (or empty) inherits the parent's own assignees
    // server-side; the form never resends a wire field it left untouched.
    subtasks: z.array(
      z.object({
        title: z.string(),
        end_date: z.string().nullable(),
        assignee_ids: z.array(z.number()),
      }),
    ),
  }
}

/** Spec 0155 D-3: the server's own cap on a single create's bulk sub-tasks. */
export const MAX_TASK_FORM_SUBTASKS = 50

/** Narrower mirror of the `recurrence` object, just what `addRecurrenceIssues` reads. */
interface RefinedRecurrenceValues {
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

/** Narrower mirror of one `subtasks[]` row, just what `addSubtaskRowIssues` reads. */
interface RefinedSubtaskRowValues {
  title: string
}

/** Values the refinements below read; narrower than the whole form. */
interface RefinedValues {
  task_status_id: number | null
  requester_id: number | null
  task_type_id: number | null
  task_priority_id: number | null
  task_importance_id: number | null
  start_date: string | null
  end_date: string | null
  assignee_ids: number[]
  watcher_ids: number[]
  recurrence: RefinedRecurrenceValues
  subtasks: RefinedSubtaskRowValues[]
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

/**
 * Spec 0154 D-8: the server precompiles a default row on create when one of
 * these is omitted, so a "missing" value on the wire never happens any more
 * — but the FORM still requires an explicit pick in both modes, mirroring
 * the same requiredness the seed guarantees exactly one default row for.
 */
function addMissingLookupIssue(
  value: number | null,
  path: string,
  message: string,
  ctx: z.RefinementCtx,
): void {
  if (value === null) {
    ctx.addIssue({ code: 'custom', path: [path], message })
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
 * Spec 0120 D-1/AC-033: the write path's conditional rules, replicated so the
 * user sees the missing field inline instead of waiting for the server's
 * 422. Every check is SKIPPED while `enabled` is false — a disabled section
 * carries no rule to validate, and `end_date` itself is already covered
 * unconditionally by `addMissingEndDateIssue` above (D-1: a recurrence with
 * no scadenza is not calculable, but the form already requires one either way).
 */
function addRecurrenceIssues(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
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

/**
 * Spec 0155 D-3: a row added to the "Sottotask" block needs at least a
 * title — every other field is optional and inherits from the parent when
 * left blank. `subtasks` stays empty on edit (the section only renders on
 * create), so this is a no-op there.
 */
function addSubtaskRowIssues(values: RefinedValues, ctx: z.RefinementCtx, t: TFunction): void {
  values.subtasks.forEach((row, index) => {
    if (row.title.trim() === '') {
      ctx.addIssue({
        code: 'custom',
        path: ['subtasks', index, 'title'],
        message: t('tasks.form.subtasks.titleRequired'),
      })
    }
  })
}

/**
 * Builds the task form schema. One schema for create and edit — the partial
 * PATCH diff is computed by the payload builder, not by a second shape that
 * could drift.
 *
 * `isCreate` (spec 0118 D-1/D-3) is the only thing that branches by mode:
 * `task_status_id` is required in edit only (the server derives it on
 * create, D-3/D-4), while `requester_id`/`assignee_ids`/`end_date` and the
 * watcher-overlap rule (D-9) apply in BOTH modes — a PATCH that submits them
 * is bound by the same requiredness as a POST (D-2).
 *
 * Spec 0121 RECTIFIES this schema: the closure-feedback requiredness rule
 * that used to branch on the picked status' phase is GONE along with the
 * `closure_feedback` field itself (D-7) — the feedback is written only from
 * the completion pop-up now, so this form no longer needs the status' phase
 * at all.
 *
 * `parentDateRange` (spec 0123 D-7) is `null` outside "crea sotto-task"
 * (`use-task-parent-prefill.ts` only resolves it on create, see there).
 */
export function buildTaskSchema(
  t: TFunction,
  isCreate: boolean = false,
  parentDateRange: ParentDateRange | null = null,
) {
  return z.object(baseFields(t)).superRefine((values, ctx) => {
    if (!isCreate) {
      addMissingStatusIssue(values, ctx, t)
    }
    addMissingRequesterIssue(values, ctx, t)
    addMissingAssigneesIssue(values, ctx, t)
    addMissingEndDateIssue(values, ctx, t)
    addMissingLookupIssue(values.task_type_id, 'task_type_id', t('tasks.form.typeRequired'), ctx)
    addMissingLookupIssue(values.task_priority_id, 'task_priority_id', t('tasks.form.priorityRequired'), ctx)
    addMissingLookupIssue(
      values.task_importance_id,
      'task_importance_id',
      t('tasks.form.importanceRequired'),
      ctx,
    )
    addWatcherOverlapIssue(values, ctx, t)
    addRecurrenceIssues(values, ctx, t)
    addSubtaskRowIssues(values, ctx, t)
    addParentDateRangeIssues(values, ctx, t, parentDateRange)
  })
}

export type TaskFormValues = z.infer<ReturnType<typeof buildTaskSchema>>
