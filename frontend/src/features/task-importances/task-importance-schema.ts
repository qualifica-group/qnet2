import { z } from 'zod'
import type { TFunction } from 'i18next'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { isKnownIconName } from '@/features/custom-fields/icon-catalog'

/**
 * Zod schema for the task importance create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0101 `data_contract`) 1:1.
 * `color` stores a palette TOKEN and is REQUIRED (D-4); `icon` stores a
 * curated lucide name with the empty string meaning "unset" (mapped to `null`
 * by the payload builder). Both are checked against the same allow-lists the
 * backend validates with, so this is defense in depth, not the only guard.
 * `sort_order` is server-managed and has no form field.
 */

/** Backend `name` column limit (`max:191`, unique). */
const NAME_MAX_LENGTH = 191

/** Backend `description` column limit (`max:500`). */
const DESCRIPTION_MAX_LENGTH = 500

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('taskImportances.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('taskImportances.form.nameMax')),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('taskImportances.form.descriptionMax'))
      .nullable(),
    color: z
      .string()
      .min(1, t('taskImportances.form.colorRequired'))
      .refine((token) => swatchClassFor(token) !== undefined, t('taskImportances.form.colorInvalid')),
    icon: z
      .string()
      .refine((name) => name === '' || isKnownIconName(name), t('taskImportances.form.iconInvalid')),
    is_active: z.boolean(),
  }
}

/** Create schema. */
export function buildCreateTaskImportanceSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; the partial PATCH is computed by the caller). */
export function buildUpdateTaskImportanceSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateTaskImportanceFormValues = z.infer<ReturnType<typeof buildCreateTaskImportanceSchema>>
export type UpdateTaskImportanceFormValues = z.infer<ReturnType<typeof buildUpdateTaskImportanceSchema>>
