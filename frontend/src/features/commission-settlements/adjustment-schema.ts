import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema of the adjustment form (spec 0213 D-7), a factory so messages are
 * localized. The amount is signed (credit/debit) with at most 2 decimals and
 * must not be zero; notes are mandatory. `requireParties` is true only in
 * standalone mode, where the user picks agent and company.
 */

const NOTES_MAX_LENGTH = 500
const SIGNED_AMOUNT_PATTERN = /^-?\d{1,13}(\.\d{1,2})?$/

export function buildAdjustmentSchema(t: TFunction, requireParties: boolean) {
  return z
    .object({
      referent_id: z.number().nullable(),
      company_id: z.number().nullable(),
      amount: z
        .string()
        .trim()
        .min(1, t('commissionSettlements.adjustment.amountRequired'))
        .refine((value) => SIGNED_AMOUNT_PATTERN.test(value), {
          message: t('commissionSettlements.adjustment.amountInvalid'),
        })
        .refine((value) => Number(value) !== 0, { message: t('commissionSettlements.adjustment.amountZero') }),
      accrued_on: z.string().min(1, t('commissionSettlements.adjustment.dateRequired')),
      notes: z
        .string()
        .trim()
        .min(1, t('commissionSettlements.adjustment.notesRequired'))
        .max(NOTES_MAX_LENGTH, t('commissionSettlements.adjustment.notesMax')),
    })
    .superRefine((values, context) => {
      if (!requireParties) {
        return
      }
      if (values.referent_id === null) {
        context.addIssue({
          code: 'custom',
          path: ['referent_id'],
          message: t('commissionSettlements.adjustment.referentRequired'),
        })
      }
      if (values.company_id === null) {
        context.addIssue({
          code: 'custom',
          path: ['company_id'],
          message: t('commissionSettlements.adjustment.companyRequired'),
        })
      }
    })
}

type AdjustmentSchema = ReturnType<typeof buildAdjustmentSchema>

export type AdjustmentFormValues = z.input<AdjustmentSchema>
export type AdjustmentSchemaOutput = z.output<AdjustmentSchema>
