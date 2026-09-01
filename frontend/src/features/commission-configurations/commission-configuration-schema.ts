import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  COMMISSION_RECIPIENT_TYPES,
  COMMISSION_ROLE_ALLOWED_RECIPIENT_TYPES,
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
      // Chosen by the operator among `recipient_role`'s allow-list (spec 0090 D-4).
      recipient_type: z.enum(COMMISSION_RECIPIENT_TYPES),
      recipient_id: z.number().nullable(),
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
      if (values.application_scope === 'RECIPIENT' && values.recipient_id === null) {
        ctx.addIssue({
          code: 'custom',
          path: ['recipient_id'],
          message: t('commissionConfigurations.form.errors.recipientRequired'),
        })
      }
      if (values.valid_until && values.valid_until < values.valid_from) {
        ctx.addIssue({
          code: 'custom',
          path: ['valid_until'],
          message: t('commissionConfigurations.form.errors.validUntilInvalid'),
        })
      }
      // Mirrors the server-side allow-list (D-4): a mismatch here means the
      // UI let a role/type combination through it should not have.
      if (!COMMISSION_ROLE_ALLOWED_RECIPIENT_TYPES[values.recipient_role].includes(values.recipient_type)) {
        ctx.addIssue({
          code: 'custom',
          path: ['recipient_type'],
          message: t('commissionConfigurations.form.errors.recipientTypeInvalid'),
        })
      }
    })
}

export type CommissionConfigurationFormValues = z.infer<
  ReturnType<typeof buildCommissionConfigurationSchema>
>
