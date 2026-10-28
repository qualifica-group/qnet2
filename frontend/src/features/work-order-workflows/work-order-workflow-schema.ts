import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { WorkOrderStatusFormRow } from '@/features/work-order-workflows/types'

/** Backend `work_order_workflows.name` column limit (`max:255`). */
const NAME_MAX_LENGTH = 191

/** A required string field: `null`/empty (unselected) fails the refine. */
function requiredField(message: string) {
  return z
    .string()
    .nullable()
    .refine((value): boolean => value !== null && value !== '', { message })
}

/** A required relation id: `null` (unset) fails the refine. */
function requiredValueId(message: string) {
  return z
    .number()
    .nullable()
    .refine((value): boolean => value !== null, { message })
}

/**
 * Zod schema of the work order workflow form (create and in-place edit share
 * it), built as a factory so messages are localized. `criteria` needs at
 * least one row with a DISTINCT `field` and a picked `value_id`; `statuses`
 * is a plain value edited through `<SortableList>` where every row needs a name.
 */
export function buildWorkOrderWorkflowSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .trim()
      .min(1, t('workOrderWorkflows.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('workOrderWorkflows.form.nameMax')),
    is_active: z.boolean(),
    criteria: z
      .array(
        z.object({
          field: requiredField(t('workOrderWorkflows.form.criteria.fieldRequired')),
          value_id: requiredValueId(t('workOrderWorkflows.form.criteria.valueRequired')),
        }),
      )
      .min(1, t('workOrderWorkflows.form.criteria.required'))
      .superRefine((rows, ctx) => {
        const seenFields = new Set<string>()
        rows.forEach((row, index) => {
          if (row.field === null || row.field === '') {
            return
          }
          if (seenFields.has(row.field)) {
            ctx.addIssue({
              code: z.ZodIssueCode.custom,
              message: t('workOrderWorkflows.form.criteria.duplicateField'),
              path: [index, 'field'],
            })
            return
          }
          seenFields.add(row.field)
        })
      }),
    statuses: z
      .array(z.custom<WorkOrderStatusFormRow>())
      .refine((rows) => rows.every((row) => row.name.trim() !== ''), {
        message: t('workOrderWorkflows.form.statuses.nameRequired'),
      }),
  })
}

export type WorkOrderWorkflowFormValues = z.infer<ReturnType<typeof buildWorkOrderWorkflowSchema>>
