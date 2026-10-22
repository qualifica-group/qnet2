import { z } from 'zod'
import type { TFunction } from 'i18next'
import { COMPANY_CLOSURE_KINDS, COMPANY_CLOSURE_MAX_DAYS } from '@/features/company-closures/types'

/** Backend `name` limit (`max:100`, spec 0304). */
const NAME_MAX_LENGTH = 100
const MS_PER_DAY = 86_400_000

/** Whole days from `from` to `to` (both `Y-m-d`); NaN when either is not a date. */
export function daysBetween(from: string, to: string): number {
  return (Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`)) / MS_PER_DAY
}

/** Zod schema of the closure form, a factory so the messages are localized. */
export function buildCompanyClosureSchema(t: TFunction) {
  return z
    .object({
      name: z
        .string()
        .trim()
        .min(1, t('companyClosures.form.nameRequired'))
        .max(NAME_MAX_LENGTH, t('companyClosures.form.nameMax')),
      starts_on: z.string().min(1, t('companyClosures.form.startsOnRequired')),
      ends_on: z.string().min(1, t('companyClosures.form.endsOnRequired')),
      kind: z.enum(COMPANY_CLOSURE_KINDS),
      absence_type_id: z.number().int().nullable(),
      applies_to_all_sites: z.boolean(),
      operational_site_ids: z.array(z.number().int()),
      repeats_yearly: z.boolean(),
    })
    .superRefine((values, ctx) => {
      const span = daysBetween(values.starts_on, values.ends_on)
      if (span < 0) {
        ctx.addIssue({ code: 'custom', path: ['ends_on'], message: t('companyClosures.form.endsBeforeStart') })
      } else if (span + 1 > COMPANY_CLOSURE_MAX_DAYS) {
        ctx.addIssue({ code: 'custom', path: ['ends_on'], message: t('companyClosures.form.spanTooLong') })
      }
      if (values.kind === 'collective_leave' && values.absence_type_id === null) {
        ctx.addIssue({ code: 'custom', path: ['absence_type_id'], message: t('companyClosures.form.absenceTypeRequired') })
      }
      if (!values.applies_to_all_sites && values.operational_site_ids.length === 0) {
        ctx.addIssue({ code: 'custom', path: ['operational_site_ids'], message: t('companyClosures.form.sitesRequired') })
      }
    })
}

export type CompanyClosureFormValues = z.infer<ReturnType<typeof buildCompanyClosureSchema>>

/** The yearly recurrence only makes sense for a single-day holiday (spec 0304 D-13). */
export function canRepeatYearly(values: Pick<CompanyClosureFormValues, 'kind' | 'starts_on' | 'ends_on'>): boolean {
  return values.kind === 'holiday' && values.starts_on !== '' && values.starts_on === values.ends_on
}
