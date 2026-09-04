import { z } from 'zod'
import type { TFunction } from 'i18next'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { isKnownIconName } from '@/features/custom-fields/icon-catalog'

/**
 * Zod schema for the task status create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0101 `data_contract`) 1:1.
 * `color` stores a palette TOKEN and is REQUIRED (D-4); `icon` stores a
 * curated lucide name with the empty string meaning "unset" (mapped to `null`
 * by the payload builder). Both are checked against the same allow-lists the
 * backend validates with, so this is defense in depth, not the only guard.
 * `sort_order` and `system_key` are server-managed and have no form field.
 */

/** Backend `name` column limit (`max:191`, unique). */
const NAME_MAX_LENGTH = 191

/** Backend `description` column limit (`max:500`). */
const DESCRIPTION_MAX_LENGTH = 500

/** Backend `completion_percentage` range (unsignedTinyInteger, D-4). */
const COMPLETION_PERCENTAGE_MIN = 0
const COMPLETION_PERCENTAGE_MAX = 100

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('taskStatuses.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('taskStatuses.form.nameMax')),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('taskStatuses.form.descriptionMax'))
      .nullable(),
    color: z
      .string()
      .min(1, t('taskStatuses.form.colorRequired'))
      .refine((token) => swatchClassFor(token) !== undefined, t('taskStatuses.form.colorInvalid')),
    icon: z
      .string()
      .refine((name) => name === '' || isKnownIconName(name), t('taskStatuses.form.iconInvalid')),
    is_active: z.boolean(),
    completion_percentage: z
      .number({ error: t('taskStatuses.form.completionPercentageRequired') })
      .int(t('taskStatuses.form.completionPercentageInvalid'))
      .min(COMPLETION_PERCENTAGE_MIN, t('taskStatuses.form.completionPercentageRange'))
      .max(COMPLETION_PERCENTAGE_MAX, t('taskStatuses.form.completionPercentageRange')),
  }
}

/** Create schema. */
export function buildCreateTaskStatusSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; the partial PATCH is computed by the caller). */
export function buildUpdateTaskStatusSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateTaskStatusFormValues = z.infer<ReturnType<typeof buildCreateTaskStatusSchema>>
export type UpdateTaskStatusFormValues = z.infer<ReturnType<typeof buildUpdateTaskStatusSchema>>
