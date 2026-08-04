import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema of the proposal dialog's single field: the free-text motivation
 * behind the requested change. Mirrors the frozen backend contract (spec
 * 0078) — `reason` is `sometimes|nullable|string|max:1000` server-side. The
 * form's type derives from this schema (single source of truth).
 */

/** Backend `reason` column limit (`max:1000`). */
const REASON_MAX_LENGTH = 1000

export function buildFieldChangeRequestSchema(t: TFunction) {
  return z.object({
    reason: z
      .string()
      .max(REASON_MAX_LENGTH, t('fieldChangeRequests.dialog.reasonMax'))
      .nullable()
      .optional(),
  })
}

export type FieldChangeRequestFormValues = z.infer<
  ReturnType<typeof buildFieldChangeRequestSchema>
>

/**
 * Schema of the approve/reject confirmation dialog's single field: the
 * gestore's free-text note (`note` — `sometimes|nullable|string|max:1000`
 * server-side on both `/approve` and `/reject`). Same shape as the proposal
 * dialog's `reason` above, kept separate so each dialog's form values stay
 * named after its own backend field.
 */
export function buildHandleFieldChangeRequestSchema(t: TFunction) {
  return z.object({
    note: z
      .string()
      .max(REASON_MAX_LENGTH, t('fieldChangeRequests.dialog.reasonMax'))
      .nullable()
      .optional(),
  })
}

export type HandleFieldChangeRequestFormValues = z.infer<
  ReturnType<typeof buildHandleFieldChangeRequestSchema>
>
