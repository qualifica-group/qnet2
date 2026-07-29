import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  COMMISSION_ROLES,
  COMMISSION_SCOPES,
  COMMISSION_STATUSES,
  COMMISSION_TYPES,
} from './types'

export function buildCommissionConfigurationSchema(t: TFunction) {
  return z
    .object({
      name: z.string().trim().min(1, t('commissionConfigurations.form.errors.nameRequired')).max(191),
      recipient_role: z.enum(COMMISSION_ROLES),
      application_scope: z.enum(COMMISSION_SCOPES),
      product_category_id: z.number().nullable(),
      product_id: z.number().nullable(),
      commission_type: z.enum(COMMISSION_TYPES),
      value: z.number().min(0, t('commissionConfigurations.form.errors.valueInvalid')),
      priority: z.number().int(t('commissionConfigurations.form.errors.priorityInvalid')),
      valid_from: z.string().min(1, t('commissionConfigurations.form.errors.validFromRequired')),
      valid_until: z.string().nullable(),
      status: z.enum(COMMISSION_STATUSES),
      internal_note: z.string().max(5000).nullable(),
    })
    .superRefine((values, ctx) => {
      if (values.application_scope === 'PRODUCT_CATEGORY' && values.product_category_id === null) {
        ctx.addIssue({
          code: 'custom',
          path: ['product_category_id'],
          message: t('commissionConfigurations.form.errors.categoryRequired'),
        })
      }
      if (values.application_scope === 'PRODUCT' && values.product_id === null) {
        ctx.addIssue({
          code: 'custom',
          path: ['product_id'],
          message: t('commissionConfigurations.form.errors.productRequired'),
        })
      }
      if (values.valid_until && values.valid_until < values.valid_from) {
        ctx.addIssue({
          code: 'custom',
          path: ['valid_until'],
          message: t('commissionConfigurations.form.errors.validUntilInvalid'),
        })
      }
    })
}

export type CommissionConfigurationFormValues = z.infer<
  ReturnType<typeof buildCommissionConfigurationSchema>
>
