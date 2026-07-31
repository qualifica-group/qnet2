import { z } from 'zod'
import type { TFunction } from 'i18next'
import { CONTRACT_STATUS_GROUPS } from '@/features/status-reorder/types'

/**
 * Zod schema for the contract status create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0072 `data_contract`) 1:1.
 * `color` stores a palette TOKEN (empty string = unset, mapped to `null` by
 * the payload builder) — see `ColorTokenPicker`. `description` carries
 * `string | null` directly (the textarea maps an empty value to `null`
 * on input, same pattern as `document-layouts`). `sort_order` and
 * `system_key` are server-managed and have no form field. The
 * `is_default = true` requires `is_active = true` invariant (BR-5) is
 * enforced here, client-side, BEFORE any request is sent — mirrored
 * server-side (422) so this is defense in depth, not the only guard.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Backend `description` column limit (`max:500`). */
const DESCRIPTION_MAX_LENGTH = 500

/** Backend `color` column limit (`max:32`). */
const COLOR_MAX_LENGTH = 32

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('contractStatuses.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('contractStatuses.form.nameMax')),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('contractStatuses.form.descriptionMax'))
      .nullable(),
    color: z.string().max(COLOR_MAX_LENGTH, t('contractStatuses.form.colorMax')),
    // Fixed 4-value enum (the closed phase carries its outcome). System rows only
    // ever accept `name`/`color` — every other control is disabled for them in
    // the form body, so these fields never diverge from their hydrated value.
    group: z.enum(CONTRACT_STATUS_GROUPS),
    is_active: z.boolean(),
    is_default: z.boolean(),
  }
}

/** BR-5: a default row must be active. Reported on `is_active` regardless of which switch the user last touched. */
function requireActiveWhenDefault(t: TFunction) {
  return (values: { is_active: boolean; is_default: boolean }, ctx: z.RefinementCtx) => {
    if (values.is_default && !values.is_active) {
      ctx.addIssue({
        code: 'custom',
        path: ['is_active'],
        message: t('contractStatuses.form.defaultRequiresActive'),
      })
    }
  }
}

/** Create schema. */
export function buildCreateContractStatusSchema(t: TFunction) {
  return z.object(baseFields(t)).superRefine(requireActiveWhenDefault(t))
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateContractStatusSchema(t: TFunction) {
  return z.object(baseFields(t)).superRefine(requireActiveWhenDefault(t))
}

export type CreateContractStatusFormValues = z.infer<
  ReturnType<typeof buildCreateContractStatusSchema>
>
export type UpdateContractStatusFormValues = z.infer<
  ReturnType<typeof buildUpdateContractStatusSchema>
>
