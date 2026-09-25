/**
 * Recursive shapes for the create form's "Sottotask" block (spec 0161 D-1): a
 * tree of rows up to 3 levels below the task being created — figlio, nipote,
 * pronipote — 50 nodes total across the whole tree (D-1, counted at every
 * level). Split out of `types.ts` (492 lines) and `task-schema.ts` (both near
 * the soft file-size limit) since the RHF form values and the wire payload
 * need the SAME shape at every level.
 *
 * The form-row types below are bounded to exactly 3 CONCRETE levels — never a
 * self-referencing type — so `Path`/`FieldPath`/`FieldArrayPath` of
 * `TaskFormValues` (react-hook-form) stay a finite union: a genuinely
 * self-referential field type is a documented "type instantiation is
 * excessively deep" trap for those generics. `CreateTaskSubtaskPayload`
 * itself stays self-referential on purpose — it is a plain wire shape no
 * react-hook-form generic ever operates on, so the recursion costs nothing,
 * and it mirrors the backend's own `SubtaskInput` contract (frozen in spec
 * 0161's `data_contract`) verbatim.
 */

/** A "pronipote" row (level 3, the deepest): no further `subtasks` field at all — the section renders no "add child" button here (D-1). */
export interface SubtaskGreatGrandchildValues {
  title: string
  end_date: string | null
  assignee_ids: number[]
}

/** A "nipote" row (level 2): may carry its own "pronipote" children. */
export interface SubtaskGrandchildValues {
  title: string
  end_date: string | null
  assignee_ids: number[]
  subtasks: SubtaskGreatGrandchildValues[]
}

/** A "figlio" row (level 1, `TaskFormValues['subtasks']` itself): may carry its own "nipote" children. */
export interface SubtaskChildValues {
  title: string
  end_date: string | null
  assignee_ids: number[]
  subtasks: SubtaskGrandchildValues[]
}

/**
 * One row of `CreateTaskPayload.subtasks` (spec 0155 D-3, extended by spec
 * 0161 D-1 with the recursive `subtasks` field, same 3-level bound as the
 * form values above — the builder in `task-form-payload.ts` only ever
 * recurses 3 deep because its own input, `SubtaskChildValues`, is itself
 * bounded that way). The compact "Sottotask" block in the create form only
 * ever populates `title`, `end_date`, `assignee_ids` and nested `subtasks`
 * (`task-form-subtasks-section.tsx`); the rest of this shape exists because
 * the endpoint accepts it, not because the form does.
 */
export interface CreateTaskSubtaskPayload {
  title: string
  description?: string | null
  start_date?: string | null
  end_date?: string | null
  estimated_minutes?: number | null
  assignee_ids?: number[]
  watcher_ids?: number[]
  task_type_id?: number | null
  task_priority_id?: number | null
  task_importance_id?: number | null
  task_category_id?: number | null
  subtasks?: CreateTaskSubtaskPayload[]
}

/**
 * The minimal recursive shape `countSubtaskTreeNodes` needs — every one of
 * the 3 bounded row types above satisfies it structurally. `title` is
 * REQUIRED here (every level carries one) so TS's weak-type check has at
 * least one property in common with e.g. `SubtaskGreatGrandchildValues`,
 * which has no `subtasks` field at all — an all-optional `SubtaskTreeNode`
 * would otherwise reject it as having "no properties in common".
 */
interface SubtaskTreeNode {
  title: string
  subtasks?: SubtaskTreeNode[]
}

/**
 * Counts every node across the WHOLE tree, every level included (spec 0161
 * D-1's 50-node cap, "contati a ogni livello"). Shared by the schema's own
 * superRefine (`task-schema.ts`) and the section's live "add child" gating
 * (`task-form-subtasks-section.tsx`) so the two never disagree on the count.
 */
export function countSubtaskTreeNodes(rows: readonly SubtaskTreeNode[]): number {
  return rows.reduce((total, row) => total + 1 + countSubtaskTreeNodes(row.subtasks ?? []), 0)
}
