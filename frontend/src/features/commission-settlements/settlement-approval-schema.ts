import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { ApprovalDraft, SettlementApprovePayload } from '@/features/commission-settlements/types'

/** `'create'` = create the supplier registry from the referent; a number = link that existing registry. */
export const CREATE_SUPPLIER_CHOICE = 'create'

/**
 * Zod schema of the "Approva" dialog (spec 0267 D-4/D-8), a factory so messages
 * are localized. Payment method, cost type and VAT rate are required; the
 * financial account is optional; the supplier choice is required only while the
 * agent has no supplier registry linked yet.
 */
export function buildApprovalSchema(t: TFunction, needsSupplier: boolean) {
  return z
    .object({
      payment_method_id: z.number().nullable(),
      cost_type_id: z.number().nullable(),
      vat_rate_id: z.number().nullable(),
      financial_account_id: z.number().nullable(),
      supplier_choice: z.union([z.number(), z.literal(CREATE_SUPPLIER_CHOICE)]).nullable(),
    })
    .superRefine((values, context) => {
      const required = [
        ['payment_method_id', 'paymentMethodRequired'],
        ['cost_type_id', 'costTypeRequired'],
        ['vat_rate_id', 'vatRateRequired'],
      ] as const
      for (const [path, messageKey] of required) {
        if (values[path] === null) {
          context.addIssue({ code: 'custom', path: [path], message: t(`commissionSettlements.approvalDialog.${messageKey}`) })
        }
      }
      if (needsSupplier && values.supplier_choice === null) {
        context.addIssue({
          code: 'custom',
          path: ['supplier_choice'],
          message: t('commissionSettlements.approvalDialog.supplierRequired'),
        })
      }
    })
}

type ApprovalSchema = ReturnType<typeof buildApprovalSchema>

export type ApprovalFormValues = z.input<ApprovalSchema>
export type ApprovalSchemaOutput = z.output<ApprovalSchema>

/** Defaults come from the agent's fiscal profile, through the approval draft. */
export function buildApprovalDefaults(draft: ApprovalDraft): ApprovalFormValues {
  return {
    payment_method_id: draft.payment_method_id,
    cost_type_id: draft.cost_type_id,
    vat_rate_id: draft.vat_rate_id,
    financial_account_id: null,
    supplier_choice: null,
  }
}

/** The approve body, or `null` while a required id is still missing (the schema already blocks that). */
export function buildApprovePayload(values: ApprovalSchemaOutput): SettlementApprovePayload | null {
  if (values.payment_method_id === null || values.cost_type_id === null || values.vat_rate_id === null) {
    return null
  }
  return {
    payment_method_id: values.payment_method_id,
    cost_type_id: values.cost_type_id,
    vat_rate_id: values.vat_rate_id,
    financial_account_id: values.financial_account_id,
  }
}
