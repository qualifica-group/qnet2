import { z } from 'zod'
import type { TFunction } from 'i18next'
import { COMMISSION_RECIPIENT_ROLES } from '@/features/commission-settlement-runs/types'

/**
 * Zod schema of the "Nuovo lancio" form (spec 0213 data_contract), a factory
 * so messages are localized. `period_to >= period_from` mirrors the server rule;
 * `min_amount` stays a string (decimal input), empty = use the server default.
 */

const AMOUNT_PATTERN = /^\d{1,13}(\.\d{1,2})?$/

export function buildCommissionSettlementRunSchema(t: TFunction) {
  return z
    .object({
      period_from: z.string().min(1, t('commissionSettlementRuns.form.periodFromRequired')),
      period_to: z.string().min(1, t('commissionSettlementRuns.form.periodToRequired')),
      company_id: z.number().nullable(),
      referent_ids: z.array(z.number()),
      recipient_roles: z.array(z.enum(COMMISSION_RECIPIENT_ROLES)),
      min_amount: z
        .string()
        .trim()
        .refine((value) => value === '' || AMOUNT_PATTERN.test(value), {
          message: t('commissionSettlementRuns.form.minAmountInvalid'),
        }),
    })
    .superRefine((values, context) => {
      // ISO dates compare correctly as strings.
      if (values.period_from !== '' && values.period_to !== '' && values.period_to < values.period_from) {
        context.addIssue({
          code: 'custom',
          path: ['period_to'],
          message: t('commissionSettlementRuns.form.periodToBeforeFrom'),
        })
      }
    })
}

type CommissionSettlementRunSchema = ReturnType<typeof buildCommissionSettlementRunSchema>

export type CommissionSettlementRunFormValues = z.input<CommissionSettlementRunSchema>
export type CommissionSettlementRunSchemaOutput = z.output<CommissionSettlementRunSchema>
