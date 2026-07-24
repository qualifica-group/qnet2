import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the reward status create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0060) 1:1. `color` stores a
 * palette TOKEN and is REQUIRED (D-4, the picker's "X" reset produces `''`,
 * which `.min(1)` rejects — divergence from the `opportunity-statuses`
 * template, where `color` is optional). `description` is a free-text,
 * nullable field. `is_active` defaults to `true` on create. `sort_order` is
 * server-managed (D-3) and has no form field. This module has no custom
 * fields (spec 0060 scope: out).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Backend `color` column limit (`max:32`). */
const COLOR_MAX_LENGTH = 32

/** Backend `description` column limit (`max:500`). */
const DESCRIPTION_MAX_LENGTH = 500

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('rewardStatuses.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('rewardStatuses.form.nameMax')),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('rewardStatuses.form.descriptionMax'))
      .nullable(),
    color: z
      .string()
      .min(1, t('rewardStatuses.form.colorRequired'))
      .max(COLOR_MAX_LENGTH, t('rewardStatuses.form.colorMax')),
    is_active: z.boolean(),
  }
}

/** Create schema. */
export function buildCreateRewardStatusSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateRewardStatusSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateRewardStatusFormValues = z.infer<ReturnType<typeof buildCreateRewardStatusSchema>>
export type UpdateRewardStatusFormValues = z.infer<ReturnType<typeof buildUpdateRewardStatusSchema>>
