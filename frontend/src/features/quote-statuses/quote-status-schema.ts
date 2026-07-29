import { z } from 'zod'
import type { TFunction } from 'i18next'
import { STATUS_GROUPS } from '@/features/status-reorder/types'

/**
 * Zod schema for the quote status create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0065) 1:1. `color` stores a
 * palette TOKEN (empty string = unset, mapped to `null` by the payload
 * builder) — see `ColorTokenPicker`. `sort_order` is server-managed and has
 * no form field. This module has no custom fields (spec 0065 scope: out).
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
      .min(1, t('quoteStatuses.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('quoteStatuses.form.nameMax')),
    color: z.string().max(COLOR_MAX_LENGTH, t('quoteStatuses.form.colorMax')),
    // Fixed 3-value enum. System rows only ever
    // accept `name`/`color` — the group control is disabled for them in the
    // form body, so this field never diverges from its hydrated value.
    group: z.enum(STATUS_GROUPS),
  }
}

/** Create schema. */
export function buildCreateQuoteStatusSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateQuoteStatusSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateQuoteStatusFormValues = z.infer<
  ReturnType<typeof buildCreateQuoteStatusSchema>
>
export type UpdateQuoteStatusFormValues = z.infer<
  ReturnType<typeof buildUpdateQuoteStatusSchema>
>
