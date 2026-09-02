import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the lead create/edit form, built as a factory so validation
 * messages are localized via the i18n `t` function. The shape mirrors the
 * frozen backend contract (spec 0024) 1:1: no `code` (D-3).
 */

/** Backend `notes` column limit (`max:5000`); exported for the form's character counter. */
export const NOTES_MAX_LENGTH = 5000

/**
 * A single free-form `extra_fields` row as edited in the field array (spec
 * 0033, AC-014). The key is required (trimmed): an empty key cannot map to
 * a meaningful `Record<string, string>` entry, so it is rejected rather
 * than silently dropped.
 */
function extraFieldEntrySchema(t: TFunction) {
  return z.object({
    key: z.string().trim().min(1, t('leads.form.extraFields.keyRequired')),
    value: z.string(),
  })
}

/**
 * BR-1/D-1: contact and campaign are unconditionally required, so a plain
 * `refine` on the controlled-select's `null` unset state is enough — no
 * cross-field rule needed. The explicit `: boolean` return type on each
 * predicate is load-bearing, not stylistic: without it, TS 5.5+'s automatic
 * type-predicate inference treats `value !== null` as a `value is number`
 * guard, silently narrowing the field's inferred type to non-nullable
 * `number` and breaking every consumer typed against the nullable form
 * value (`RelationSelectField`'s `Control<LeadFormValues>`, `useForm`'s
 * `defaultValues`).
 */
function baseFields(t: TFunction) {
  return {
    registry_id: z
      .number()
      .nullable()
      .refine((value): boolean => value !== null, { message: t('leads.form.registryRequired') }),
    campaign_id: z
      .number()
      .nullable()
      .refine((value): boolean => value !== null, { message: t('leads.form.campaignRequired') }),
    operational_site_id: z.number().nullable(),
    source_id: z.number().nullable(),
    operator_id: z.number().nullable(),
    // Directive 2026-07-21: the Regione is a first-class, free user input —
    // always present, freely editable/clearable, never required and never
    // inherited from the chosen Sede (no auto-fill).
    state_id: z.number().nullable(),
    notes: z.string().max(NOTES_MAX_LENGTH, t('leads.form.notesMax')).nullable(),
    extra_fields: z.array(extraFieldEntrySchema(t)),
    /**
     * Products of interest (spec 0094, D-5). Deliberately NOT `.min(1)`: a
     * lead with zero products is valid (AC-036). Also deliberately NOT
     * `.default([])`: same trap documented above for `convert_to_opportunity`
     * — a Zod default on an array diverges the resolver's input/output
     * types. `use-lead-form` supplies the `[]` default instead.
     */
    products_of_interest: z.array(z.number()),
  }
}

/**
 * Create schema. A top-level `superRefine` flags duplicate `extra_fields`
 * keys (case-insensitive): two rows resolving to the same `Record` key
 * would silently overwrite one another at the API boundary, so it is
 * caught here instead.
 *
 * Directive 2026-07-21 (relaxes spec 0044 AC-008/009/041): Operator and Site
 * stay optional even when `convert_to_opportunity` is on — the derived
 * Opportunity simply inherits a null supervisor. The flag is deliberately
 * NOT `.default(false)`: a Zod default makes the schema's input and output
 * types diverge, which collapses the resolver's inference and untypes every
 * `control` in the form. `use-lead-form` supplies the `false` default instead.
 */
export function buildCreateLeadSchema(t: TFunction) {
  return z
    .object({ ...baseFields(t), convert_to_opportunity: z.boolean() })
    .superRefine((values, ctx) => {
      const seenKeys = new Set<string>()
      values.extra_fields.forEach((entry, index) => {
        const normalizedKey = entry.key.trim().toLowerCase()
        if (!normalizedKey) return
        if (seenKeys.has(normalizedKey)) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: t('leads.form.extraFields.duplicateKey'),
            path: ['extra_fields', index, 'key'],
          })
          return
        }
        seenKeys.add(normalizedKey)
      })
    })
}

/**
 * Edit schema (same shape; partial PATCH is computed by the caller).
 * `convert_to_opportunity` is create-only in the UI: never rendered, and
 * `use-lead-form` always defaults it to `false` in edit mode.
 */
export function buildUpdateLeadSchema(t: TFunction) {
  return buildCreateLeadSchema(t)
}

export type CreateLeadFormValues = z.infer<ReturnType<typeof buildCreateLeadSchema>>
export type UpdateLeadFormValues = CreateLeadFormValues
