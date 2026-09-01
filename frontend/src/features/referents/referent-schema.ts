import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'
import { REFERENT_CONTACT_SCOPES } from '@/features/referents/types'

/**
 * Zod schemas for the referent create/edit form, built as factories so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `users`/`businessFunctions`). The shapes mirror the frozen backend
 * contract (spec 0016) 1:1. The nested `personal_data` tree is NOT part of
 * this schema: like `users`, it is a buffered `PersonalDataDraft` owned by the
 * form hook, validated separately via `buildPersonalDataSchema` — the
 * referent's display `name` is derived server-side from that card.
 * `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec
 * 0021), embedded the same way in create and edit.
 */

/** Backend `notes` column limit (`max:5000`). */
const NOTES_MAX_LENGTH = 5000

const contactScopeSchema = z.enum(REFERENT_CONTACT_SCOPES)

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    // Single-select referent type (for-select standard): `null` = unset.
    referent_type_id: z.number().nullable(),
    // Single-select linked user (spec 0090 D-2): `null` = unlinked.
    user_id: z.number().nullable(),
    contact_scope: contactScopeSchema,
    // Empty string = "no notes", mapped to `null` at the payload boundary.
    notes: z.string().max(NOTES_MAX_LENGTH, t('referents.form.notesMax')),
  }
}

/** Create schema. */
export function buildCreateReferentSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateReferentSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateReferentFormValues = z.infer<ReturnType<typeof buildCreateReferentSchema>>
export type UpdateReferentFormValues = z.infer<ReturnType<typeof buildUpdateReferentSchema>>
