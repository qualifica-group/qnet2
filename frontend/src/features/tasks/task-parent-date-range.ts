import type { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * The parent's own `[start_date, end_date]` (spec 0123 D-7 client mirror,
 * `use-task-parent-prefill.ts`): `null` means either no parent is picked or
 * its detail has not loaded yet, in which case `addParentDateRangeIssues`
 * below is a no-op — the server re-asserts the rule regardless (AC-029's
 * 422 half).
 */
export interface ParentDateRange {
  start: string | null
  end: string | null
}

/** Narrower mirror of the form values this rule reads. */
interface RefinedDateValues {
  start_date: string | null
  end_date: string | null
}

/**
 * Spec 0123 D-7 client mirror: a non-null `start_date`/`end_date` outside the
 * parent's own range 422s server-side either way — this only lets the user
 * see it before submitting. A null parent bound does not constrain (D-7). A
 * plain lexicographic compare is valid: both sides are `Y-m-d` strings.
 */
export function addParentDateRangeIssues(
  values: RefinedDateValues,
  ctx: z.RefinementCtx,
  t: TFunction,
  parentDateRange: ParentDateRange | null,
): void {
  if (!parentDateRange) {
    return
  }
  const { start: parentStart, end: parentEnd } = parentDateRange

  if (
    values.start_date &&
    ((parentStart && values.start_date < parentStart) || (parentEnd && values.start_date > parentEnd))
  ) {
    ctx.addIssue({
      code: 'custom',
      path: ['start_date'],
      message: t('tasks.form.startDateOutsideParentRange'),
    })
  }

  if (
    values.end_date &&
    ((parentStart && values.end_date < parentStart) || (parentEnd && values.end_date > parentEnd))
  ) {
    ctx.addIssue({
      code: 'custom',
      path: ['end_date'],
      message: t('tasks.form.endDateOutsideParentRange'),
    })
  }
}
