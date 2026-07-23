import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the reward type create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0058) 1:1: `name` and `color` are
 * BOTH required (D-5, the sole divergence from the `opportunity-statuses`
 * template, where `color` is optional). `color` stores a palette TOKEN — see
 * `ColorTokenPicker` — and the picker's "X" reset produces `''`, which
 * `.min(1)` rejects (D-5b). This module has no custom fields (spec 0058
 * scope: out).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Backend `color` column limit (`max:32`). */
const COLOR_MAX_LENGTH = 32

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('rewardTypes.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('rewardTypes.form.nameMax')),
    color: z
      .string()
      .min(1, t('rewardTypes.form.colorRequired'))
      .max(COLOR_MAX_LENGTH, t('rewardTypes.form.colorMax')),
  }
}

/** Create schema. */
export function buildCreateRewardTypeSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateRewardTypeSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateRewardTypeFormValues = z.infer<ReturnType<typeof buildCreateRewardTypeSchema>>
export type UpdateRewardTypeFormValues = z.infer<ReturnType<typeof buildUpdateRewardTypeSchema>>
