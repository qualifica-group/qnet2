import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  countSubtaskTreeNodes,
  type SubtaskChildValues,
  type SubtaskGrandchildValues,
  type SubtaskGreatGrandchildValues,
} from '@/features/tasks/task-subtask-types'

/**
 * Spec 0161 D-1: the "Sottotask" block's row schema, bounded to exactly 3
 * concrete levels (figlio/nipote/pronipote) rather than a self-referencing
 * `z.lazy()` — a genuinely recursive field type is a documented "excessively
 * deep" trap for react-hook-form's `Path`/`FieldArrayPath` generics, and D-1
 * itself never asks for more than 3 levels anyway. `satisfies` (not a `:
 * z.ZodType<...>` annotation) checks each schema against the matching
 * bounded interface in `task-subtask-types.ts` WITHOUT widening it to the
 * abstract `ZodType` base — that widening was erasing the nested nested
 * `subtasks` arrays down to `unknown[]` for `z.infer`, breaking every
 * react-hook-form generic keyed off `TaskFormValues`.
 */
export const subtaskGreatGrandchildRowSchema = z.object({
  title: z.string(),
  end_date: z.string().nullable(),
  assignee_ids: z.array(z.number()),
}) satisfies z.ZodType<SubtaskGreatGrandchildValues>

export const subtaskGrandchildRowSchema = z.object({
  title: z.string(),
  end_date: z.string().nullable(),
  assignee_ids: z.array(z.number()),
  subtasks: z.array(subtaskGreatGrandchildRowSchema),
}) satisfies z.ZodType<SubtaskGrandchildValues>

export const subtaskChildRowSchema = z.object({
  title: z.string(),
  end_date: z.string().nullable(),
  assignee_ids: z.array(z.number()),
  subtasks: z.array(subtaskGrandchildRowSchema),
}) satisfies z.ZodType<SubtaskChildValues>

/** Spec 0155 D-3, unchanged by spec 0161 D-1: the server's own cap on the WHOLE subtask tree, every level counted. */
export const MAX_TASK_FORM_SUBTASKS = 50

/** Spec 0161 D-1: figlio/nipote/pronipote — the deepest a row may go; the section shows no "add child" button at this depth. */
export const MAX_SUBTASK_DEPTH = 3

/**
 * Narrower mirror of one `subtasks[]` row, recursive like the schema itself —
 * just what `addSubtaskRowIssues`/`countSubtaskTreeNodes` read. `subtasks` is
 * OPTIONAL here (unlike the bounded `SubtaskChildValues`/`SubtaskGrandchildValues`
 * it mirrors): the level-3 row has no such field at all, and this interface
 * has to structurally accept every one of the 3 bounded levels alike.
 */
export interface RefinedSubtaskRowValues {
  title: string
  subtasks?: RefinedSubtaskRowValues[]
}

/**
 * Spec 0155 D-3: every row of the "Sottotask" block needs at least a title —
 * every other field is optional and inherits from the parent when left
 * blank. Spec 0161 D-1: walks EVERY level of the tree, not just the root
 * (`path` grows `subtasks`/index alternately as it descends), so a blank
 * title at the 3rd level surfaces on that exact row, not the root's.
 */
function addSubtaskRowIssues(
  rows: RefinedSubtaskRowValues[],
  path: (string | number)[],
  ctx: z.RefinementCtx,
  t: TFunction,
): void {
  rows.forEach((row, index) => {
    const rowPath = [...path, index]
    if (row.title.trim() === '') {
      ctx.addIssue({
        code: 'custom',
        path: [...rowPath, 'title'],
        message: t('tasks.form.subtasks.titleRequired'),
      })
    }
    addSubtaskRowIssues(row.subtasks ?? [], [...rowPath, 'subtasks'], ctx, t)
  })
}

/** Narrower mirror of the form values `addSubtaskTreeIssues` needs. */
interface SubtaskTreeRefineValues {
  subtasks: RefinedSubtaskRowValues[]
}

/**
 * Spec 0161 D-1: the 50-node cap counted across the WHOLE tree (every
 * level), not just the root array — `countSubtaskTreeNodes` is the single
 * shared counter the section's own "add child" gating reuses, so the two can
 * never disagree on what counts as a node.
 */
export function addSubtaskTreeIssues(
  values: SubtaskTreeRefineValues,
  ctx: z.RefinementCtx,
  t: TFunction,
): void {
  addSubtaskRowIssues(values.subtasks, ['subtasks'], ctx, t)
  if (countSubtaskTreeNodes(values.subtasks) > MAX_TASK_FORM_SUBTASKS) {
    ctx.addIssue({
      code: 'custom',
      path: ['subtasks'],
      message: t('tasks.form.subtasks.maxReached'),
    })
  }
}
