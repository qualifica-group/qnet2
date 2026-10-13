import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { WorkflowStatusFormRow } from '@/features/quote-workflows/types'

/**
 * Zod schema for the opportunity workflow create/edit form (spec 0047 Lane
 * C), built as a factory so validation messages are localized. Mirrors the
 * frozen backend contract: `criteria` requires at least one row with a
 * DISTINCT `field` and a picked `value_id` (AC-008). `statuses` (the
 * pinned open/closed + reorderable custom rows) is a plain form value edited
 * through `<SortableList>`, so a cancelled row restores it with the rest of
 * the form; every row needs a name.
 */

/** Backend `quote_workflows.name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/**
 * A required string field: `null`/empty (unselected) fails the refine. See
 * `opportunities/opportunity-schema.ts`'s `requiredRelationId` for why the
 * explicit `: boolean` return type on the predicate is load-bearing (TS
 * 5.5+'s automatic type-predicate inference would otherwise narrow the field
 * to non-nullable `string`, breaking every consumer typed against the
 * nullable form value).
 */
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

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .trim()
      .min(1, t('quoteWorkflows.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('quoteWorkflows.form.nameMax')),
    is_active: z.boolean(),
    criteria: z
      .array(
        z.object({
          field: requiredField(t('quoteWorkflows.form.criteria.fieldRequired')),
          value_id: requiredValueId(t('quoteWorkflows.form.criteria.valueRequired')),
        }),
      )
      .min(1, t('quoteWorkflows.form.criteria.required'))
      .superRefine((rows, ctx) => {
        const seenFields = new Set<string>()
        rows.forEach((row, index) => {
          if (row.field === null || row.field === '') {
            return
          }
          if (seenFields.has(row.field)) {
            ctx.addIssue({
              code: z.ZodIssueCode.custom,
              message: t('quoteWorkflows.form.criteria.duplicateField'),
              path: [index, 'field'],
            })
            return
          }
          seenFields.add(row.field)
        })
      }),
    statuses: z
      .array(z.custom<WorkflowStatusFormRow>())
      .refine((rows) => rows.every((row) => row.name.trim() !== ''), {
        message: t('quoteWorkflows.form.statuses.nameRequired'),
      }),
  }
}

/** Create schema. */
export function buildCreateQuoteWorkflowSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; `criteria` stays a required, min:1 authoritative sync). */
export function buildUpdateQuoteWorkflowSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateQuoteWorkflowFormValues = z.infer<
  ReturnType<typeof buildCreateQuoteWorkflowSchema>
>
export type UpdateQuoteWorkflowFormValues = z.infer<
  ReturnType<typeof buildUpdateQuoteWorkflowSchema>
>
