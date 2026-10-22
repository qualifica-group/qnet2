import { z } from 'zod'
import type { TFunction } from 'i18next'
import { hoursTextToMinutes, isHoursText } from '@/features/absences/balances/balance-format'

export const ALLOWANCE_UNITS = ['days', 'hours'] as const

/** Form fields are free text (decimal days or hours); they become integer minutes only in the payload. */
export function buildAllowanceSchema(t: TFunction) {
  const invalid = t('absences.balances.form.invalidNumber')

  return z
    .object({
      use_default: z.boolean(),
      unit: z.enum(ALLOWANCE_UNITS),
      annual_value: z.string(),
      carried_hours: z.string().refine(isHoursText, t('absences.balances.form.invalidHours')),
    })
    .superRefine((values, ctx) => {
      if (values.use_default) {
        return
      }
      if (!isHoursText(values.annual_value)) {
        ctx.addIssue({ code: 'custom', path: ['annual_value'], message: invalid })
      } else if (hoursTextToMinutes(values.annual_value) < 0) {
        ctx.addIssue({ code: 'custom', path: ['annual_value'], message: t('absences.balances.form.annualNegative') })
      }
    })
}

export type AllowanceFormValues = z.infer<ReturnType<typeof buildAllowanceSchema>>
