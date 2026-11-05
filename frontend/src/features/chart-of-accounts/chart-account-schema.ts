import { z } from 'zod'
import type { TFunction } from 'i18next'
import { buildAccountCodeSchema } from '@/features/chart-of-accounts/account-code'
import type { ChartAccountLevel } from '@/features/chart-of-accounts/types'

/** Backend `name` limit of mastri, conti and revenue sub-accounts (spec 0348). */
const NAME_MAX_LENGTH = 150
const DESCRIPTION_MAX_LENGTH = 500

/** Shared by the chart account (mastro/conto) and revenue type forms. */
function baseFields(t: TFunction) {
  return {
    code: buildAccountCodeSchema(t),
    name: z.string().trim().min(1, t('chartOfAccounts.form.nameRequired')).max(NAME_MAX_LENGTH, t('chartOfAccounts.form.nameMax')),
    description: z.string().max(DESCRIPTION_MAX_LENGTH, t('chartOfAccounts.form.descriptionMax')).nullable(),
    is_active: z.boolean(),
  }
}

/** Mastro / conto form: a mastro needs its `kind`, a conto its parent mastro (`parent_id`). */
export function buildChartAccountSchema(t: TFunction, level: ChartAccountLevel) {
  return z
    .object({
      ...baseFields(t),
      kind: z.enum(['cost', 'revenue', 'financial']).optional(),
      parent_id: z.number().int().optional(),
    })
    .superRefine((values, ctx) => {
      if (level === 'group' && values.kind === undefined) {
        ctx.addIssue({ code: 'custom', path: ['kind'], message: t('chartOfAccounts.form.kindRequired') })
      }
      if (level === 'account' && values.parent_id === undefined) {
        ctx.addIssue({ code: 'custom', path: ['parent_id'], message: t('chartOfAccounts.form.groupRequired') })
      }
    })
}
export type ChartAccountFormValues = z.infer<ReturnType<typeof buildChartAccountSchema>>

/** Financial sub-account form (spec 0360). */
export function buildTreasuryTypeSchema(t: TFunction) {
  return z.object({
    ...baseFields(t),
    chart_account_id: z.number({ message: t('chartOfAccounts.form.accountRequired') }).int(),
  })
}
export type TreasuryTypeFormValues = z.infer<ReturnType<typeof buildTreasuryTypeSchema>>

/** Revenue sub-account form. */
export function buildRevenueTypeSchema(t: TFunction) {
  return z.object({
    ...baseFields(t),
    chart_account_id: z.number({ message: t('chartOfAccounts.form.accountRequired') }).int(),
    // Spec 0350: optional so a missing key reads as "not the default".
    is_institution_commission_default: z.boolean().optional(),
  })
}
export type RevenueTypeFormValues = z.infer<ReturnType<typeof buildRevenueTypeSchema>>
