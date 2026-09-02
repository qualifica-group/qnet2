import { z } from 'zod'
import type { TFunction } from 'i18next'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'

/** Backend `code` column limit (`string(32)`), mirrors the quote/project pattern (D-1). */
const CODE_MAX_LENGTH = 32
/** Backend `title` column limit (`string(191)` indexed). */
const TITLE_MAX_LENGTH = 191

/** Shared fields common to create and edit; `quote_id`/`code` requiredness differs per mode below. */
function baseFields(t: TFunction) {
  return {
    code: z.string().max(CODE_MAX_LENGTH, t('workOrders.form.codeMax')),
    quote_id: z.number().nullable(),
    title: z
      .string()
      .min(1, t('workOrders.form.titleRequired'))
      .max(TITLE_MAX_LENGTH, t('workOrders.form.titleMax')),
    type: z.enum(['processing', 'project'], { message: t('workOrders.form.typeRequired') }),
    start_date: z.string().min(1, t('workOrders.form.startDateRequired')),
    // "Responsabili" (spec 0096): at least one, mirroring the backend's own
    // `supervisor_ids` required|array|min:1.
    supervisor_ids: z.array(z.number()).min(1, t('workOrders.form.supervisorsRequired')),
    // "Partecipanti": ordered, gap-aware slots — `null` is an empty slot, so
    // the array is NOT filtered before validating its length.
    participant_slots: z
      .array(z.number().nullable())
      .max(MAX_MANAGER_SLOTS, t('workOrders.form.participantsMax')),
    callback_date: z.string().nullable(),
    description: z.string().nullable(),
    internal_notes: z.string().nullable(),
    is_force_closed: z.boolean(),
    force_close_reason: z.string().nullable(),
    quote_line_ids: z.array(z.number()),
  }
}

/**
 * AC-073: "Motivo chiusura" is required exactly when "Chiusura forzata" is
 * on, mirroring the backend rule (D-4) client-side. Shared by both schemas
 * below so the rule can never drift between create and edit.
 */
function addForceCloseReasonIssue(
  values: { is_force_closed: boolean; force_close_reason: string | null },
  ctx: z.RefinementCtx,
  t: TFunction,
): void {
  if (values.is_force_closed && (values.force_close_reason ?? '').trim() === '') {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['force_close_reason'],
      message: t('workOrders.form.forceCloseReasonRequired'),
    })
  }
}

/**
 * Create schema. `code` is required (non-empty): the create form always
 * prefills it from `GET /work-orders/next-code` (D-1, mirrors `quotes`), so
 * an emptied field is a deliberate user action the client rejects rather than
 * silently falling back to server generation. `quote_id` is required: every
 * work order is created inside exactly one offer's perimeter (D-5).
 */
export function buildCreateWorkOrderSchema(t: TFunction) {
  return z
    .object({
      ...baseFields(t),
      code: z
        .string()
        .min(1, t('workOrders.form.codeRequired'))
        .max(CODE_MAX_LENGTH, t('workOrders.form.codeMax')),
    })
    .superRefine((values, ctx) => {
      if (values.quote_id === null) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['quote_id'],
          message: t('workOrders.form.quoteRequired'),
        })
      }
      addForceCloseReasonIssue(values, ctx, t)
    })
}

/**
 * Edit schema; the partial PATCH diff is computed by the caller. `code`/
 * `quote_id` stay in the shape (the form still displays them) but render
 * read-only, enforced by field permissions (D-1/D-5), never by this schema.
 */
export function buildUpdateWorkOrderSchema(t: TFunction) {
  return z.object(baseFields(t)).superRefine((values, ctx) => {
    addForceCloseReasonIssue(values, ctx, t)
  })
}

export type CreateWorkOrderFormValues = z.infer<ReturnType<typeof buildCreateWorkOrderSchema>>
export type UpdateWorkOrderFormValues = z.infer<ReturnType<typeof buildUpdateWorkOrderSchema>>
