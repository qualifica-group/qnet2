import { z } from 'zod'
import type { TFunction } from 'i18next'
import { ABSENCE_TYPE_COLORS } from '@/features/absences/types'

/** Backend `name` limit (`max:100`, spec 0278). */
const NAME_MAX_LENGTH = 100
const MIN_NOTICE_MAX_DAYS = 365
const DEFAULT_DAYS_MAX = 366

/** True when the value carries at most two decimals (floating point noise tolerated). */
const hasAtMostTwoDecimals = (value: number) => Number.isInteger(Number((value * 100).toFixed(6)))

/** Zod schema of the absence type form, a factory so the messages are localized. */
export function buildAbsenceTypeSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .trim()
      .min(1, t('absenceTypes.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('absenceTypes.form.nameMax')),
    deducts_balance: z.boolean(),
    requires_approval: z.boolean(),
    is_active: z.boolean(),
    sort_order: z
      .number({ error: t('absenceTypes.form.sortOrderInvalid') })
      .int(t('absenceTypes.form.sortOrderInvalid'))
      .min(0, t('absenceTypes.form.sortOrderInvalid')),
    allows_full_day: z.boolean(),
    allows_half_day: z.boolean(),
    allows_hourly: z.boolean(),
    min_notice_days: z
      .number({ error: t('absenceTypes.form.minNoticeInvalid') })
      .int(t('absenceTypes.form.minNoticeInvalid'))
      .min(0, t('absenceTypes.form.minNoticeInvalid'))
      .max(MIN_NOTICE_MAX_DAYS, t('absenceTypes.form.minNoticeInvalid'))
      .nullable(),
    color: z.enum(ABSENCE_TYPE_COLORS),
    default_annual_days: z
      .number({ error: t('absenceTypes.form.defaultDaysInvalid') })
      .min(0, t('absenceTypes.form.defaultDaysInvalid'))
      .max(DEFAULT_DAYS_MAX, t('absenceTypes.form.defaultDaysInvalid'))
      .refine(hasAtMostTwoDecimals, t('absenceTypes.form.defaultDaysInvalid'))
      .nullable(),
  }).refine((values) => values.allows_full_day || values.allows_half_day || values.allows_hourly, {
    message: t('absenceTypes.form.modesRequired'),
    path: ['allows_full_day'],
  })
}

export type AbsenceTypeFormValues = z.infer<ReturnType<typeof buildAbsenceTypeSchema>>
