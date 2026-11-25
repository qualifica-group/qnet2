import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  COMMISSION_RECIPIENT_TYPES,
  COMMISSION_ROLE_ALLOWED_RECIPIENT_TYPES,
  COMMISSION_ROLES,
  COMMISSION_SCOPES,
  COMMISSION_STATUSES,
  COMMISSION_TYPES,
  SUPPLIER_COMMISSION_DIRECTIONS,
} from './types'

/** Server cap on the categories/products one rule can list (spec 0430). */
const MAX_SCOPE_ITEMS = 500

export function buildCommissionConfigurationSchema(t: TFunction) {
  return z
    .object({
      name: z.string().trim().min(1, t('commissionConfigurations.form.errors.nameRequired')).max(191),
      recipient_role: z.enum(COMMISSION_ROLES),
      application_scope: z.enum(COMMISSION_SCOPES),
      product_category_ids: z.array(z.number()).max(MAX_SCOPE_ITEMS),
      product_ids: z.array(z.number()).max(MAX_SCOPE_ITEMS),
      // Chosen by the operator among `recipient_role`'s allow-list (spec 0090 D-4).
      recipient_type: z.enum(COMMISSION_RECIPIENT_TYPES),
      recipient_id: z.number().nullable(),
      commission_type: z.enum(COMMISSION_TYPES),
      supplier_commission_direction: z.enum(SUPPLIER_COMMISSION_DIRECTIONS).nullable(),
      value: z
        .number(t('commissionConfigurations.form.errors.valueInvalid'))
        .min(0, t('commissionConfigurations.form.errors.valueInvalid')),
      priority: z
        .number(t('commissionConfigurations.form.errors.priorityInvalid'))
        .int(t('commissionConfigurations.form.errors.priorityInvalid')),
      valid_from: z.string().min(1, t('commissionConfigurations.form.errors.validFromRequired')),
      valid_until: z.string().nullable(),
      status: z.enum(COMMISSION_STATUSES),
      internal_note: z.string().max(5000).nullable(),
    })
    .superRefine((values, ctx) => {
      if (values.application_scope === 'PRODUCT_CATEGORY' && values.product_category_ids.length === 0) {
        ctx.addIssue({
          code: 'custom',
          path: ['product_category_ids'],
          message: t('commissionConfigurations.form.errors.categoryRequired'),
        })
      }
      if (values.application_scope === 'PRODUCT' && values.product_ids.length === 0) {
        ctx.addIssue({
          code: 'custom',
          path: ['product_ids'],
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
      if (values.recipient_role === 'SUPPLIER' && values.supplier_commission_direction === null) {
        ctx.addIssue({
          code: 'custom',
          path: ['supplier_commission_direction'],
          message: t('commissionConfigurations.form.errors.directionRequired'),
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
