import { z } from 'zod'
import type { TFunction } from 'i18next'
import { RELATIONSHIP_TYPES } from '@/features/users/types'
import { SALARY_PERIODS, type CreateEmploymentContractPayload } from '@/features/users/contract-history-types'

/** Column bound (spec 0272): `change_reason` max length. */
const MAX_REASON_LENGTH = 255

/**
 * New contract condition form (spec 0272 D-1). Dates are `Y-m-d` strings, the
 * salary amount stays text until the payload boundary; the salary pair is
 * all-or-nothing, like the server rule (`salary_period` required with an amount).
 */
export function buildContractConditionSchema(t: TFunction) {
  return z
    .object({
      starts_on: z.string().min(1, t('users.detail.employment.contractHistory.form.startsOnRequired')),
      relationship_type: z.enum(RELATIONSHIP_TYPES),
      company_id: z.number().nullable(),
      collective_agreement_level_id: z.number().nullable(),
      salary_amount: z.string(),
      salary_period: z.enum(SALARY_PERIODS).nullable(),
      change_reason: z.string().max(MAX_REASON_LENGTH),
    })
    .superRefine((values, ctx) => {
      const amount = values.salary_amount.trim()
      if (amount !== '' && !(Number(amount) >= 0)) {
        ctx.addIssue({ code: 'custom', path: ['salary_amount'], message: t('users.detail.employment.contractHistory.form.salaryInvalid') })
      }
      if (amount !== '' && values.salary_period === null) {
        ctx.addIssue({ code: 'custom', path: ['salary_period'], message: t('users.detail.employment.contractHistory.form.salaryPeriodRequired') })
      }
    })
}

export type ContractConditionFormValues = z.infer<ReturnType<typeof buildContractConditionSchema>>

/** Salary is sent only to an actor allowed to manage it (a 403 otherwise). */
export function toContractPayload(
  values: ContractConditionFormValues,
  canManageCompensation: boolean,
): CreateEmploymentContractPayload {
  const amount = values.salary_amount.trim()
  return {
    starts_on: values.starts_on,
    relationship_type: values.relationship_type,
    company_id: values.company_id,
    collective_agreement_level_id: values.collective_agreement_level_id,
    ...(canManageCompensation && amount !== ''
      ? { salary_amount: Number(amount), salary_period: values.salary_period }
      : {}),
    change_reason: values.change_reason.trim() || null,
  }
}
