import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the document bundle create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. Mirrors the
 * frozen backend contract (spec 0175 `data_contract`) 1:1. Files themselves
 * are NOT a field of this schema: they are owned by the detail's
 * `DocumentsSection`, not this metadata form.
 */

/** Backend `name` column limit (`max:191`, unique). */
const NAME_MAX_LENGTH = 191
/** `description` column limit, same convention as every other lookup module (e.g. `task-importances`). */
const DESCRIPTION_MAX_LENGTH = 500

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('documentBundles.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('documentBundles.form.nameMax')),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('documentBundles.form.descriptionMax'))
      .nullable(),
    is_active: z.boolean(),
  }
}

/** Create schema. */
export function buildCreateDocumentBundleSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; the partial PATCH is computed by the caller). */
export function buildUpdateDocumentBundleSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateDocumentBundleFormValues = z.infer<ReturnType<typeof buildCreateDocumentBundleSchema>>
export type UpdateDocumentBundleFormValues = z.infer<ReturnType<typeof buildUpdateDocumentBundleSchema>>
