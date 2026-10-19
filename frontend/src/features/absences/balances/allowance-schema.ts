import { z } from 'zod'
import type { TFunction } from 'i18next'
import { hoursTextToMinutes, isHoursText } from '@/features/absences/balances/balance-format'

/** Form fields are free text (decimal hours); they become integer minutes only in the payload. */
export function buildAllowanceSchema(t: TFunction) {
  const invalid = t('absences.balances.form.invalidHours')

  return z.object({
    annual_hours: z
      .string()
      .refine(isHoursText, invalid)
      .refine((value) => !isHoursText(value) || hoursTextToMinutes(value) >= 0, t('absences.balances.form.annualNegative')),
    carried_hours: z.string().refine(isHoursText, invalid),
  })
}

export type AllowanceFormValues = z.infer<ReturnType<typeof buildAllowanceSchema>>
