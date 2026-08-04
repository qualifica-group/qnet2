import { z } from 'zod'
import type { TFunction } from 'i18next'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'
import { AGREEMENT_STATUSES, SIZE_CLASSES } from '@/features/registries/types'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schemas for the registry create/edit form, built as factories so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `users`/`referents`). The shapes mirror the frozen backend contract
 * (spec 0020) 1:1. The nested `personal_data` tree is NOT part of this
 * schema: like `referents`, it is a buffered `PersonalDataDraft` owned by the
 * form hook, validated separately via `buildPersonalDataSchema` — the
 * registry's display `name` is derived server-side from that card.
 */

/** Backend limit on FILLED manager slots (spec 0020 constraint, cap raised by spec 0080 A1), shared with `opportunity-schema.ts`. */
const MAX_MANAGERS = MAX_MANAGER_SLOTS

/** Backend `agreement_notes` column limit (`max:5000`). */
const AGREEMENT_NOTES_MAX_LENGTH = 5000

/** Backend `vat_group` column limit (`max:191`). */
const VAT_GROUP_MAX_LENGTH = 191

const agreementStatusSchema = z.enum(AGREEMENT_STATUSES)
const sizeClassSchema = z.enum(SIZE_CLASSES)

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    // Single-select relations (for-select standard): `null` = unset.
    source_id: z.number().nullable(),
    supervisor_id: z.number().nullable(),
    commercial_id: z.number().nullable(),
    reporter_id: z.number().nullable(),
    // Multiselect relations (for-select standard): ids, full-replace.
    sector_ids: z.array(z.number()),
    referent_ids: z.array(z.number()),
    // Ordered, gap-aware "G.A. n" manager slots: index+1 = G.A. number, `null`
    // = an intentionally empty slot. At most MAX_MANAGERS filled.
    manager_slots: z
      .array(z.number().nullable())
      .refine(
        (slots) => slots.filter((slot) => slot !== null).length <= MAX_MANAGERS,
        t('registries.form.managersMax', { max: MAX_MANAGERS }),
      ),
    // Empty string = "no VAT group", mapped to `null` at the payload boundary.
    vat_group: z.string().max(VAT_GROUP_MAX_LENGTH, t('registries.form.vatGroupMax')),
    is_supplier: z.boolean(),
    // Meaningful only when `is_supplier` is true; the form hides the toggle
    // otherwise and the server normalizes the value regardless (spec 0020).
    is_qualified_supplier: z.boolean(),
    agreement_status: agreementStatusSchema.nullable(),
    // Empty string = "no notes", mapped to `null` at the payload boundary.
    agreement_notes: z.string().max(AGREEMENT_NOTES_MAX_LENGTH, t('registries.form.agreementNotesMax')),
    size_class: sizeClassSchema.nullable(),
    employee_count: z.number().int().nonnegative(t('registries.form.employeeCountInvalid')).nullable(),
  }
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateRegistrySchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateRegistrySchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateRegistryFormValues = z.infer<ReturnType<typeof buildCreateRegistrySchema>>
export type UpdateRegistryFormValues = z.infer<ReturnType<typeof buildUpdateRegistrySchema>>
