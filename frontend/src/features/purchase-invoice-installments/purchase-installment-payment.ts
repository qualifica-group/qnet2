import { z } from 'zod'
import type { TFunction } from 'i18next'
import { isPartialCollection } from '@/features/invoices/invoice-collection-residual'
import { RESIDUAL_MODES } from '@/features/invoices/types'
import type {
  PurchaseDocumentInstallment,
  PurchasePaymentPayload,
} from '@/features/purchase-invoice-installments/types'

/** An installment carries a payment when its paid amount is set and not zero. */
export function isPaid(installment: Pick<PurchaseDocumentInstallment, 'paid_amount'>): boolean {
  return installment.paid_amount !== null && installment.paid_amount !== undefined && Number(installment.paid_amount) > 0
}

/** "Spread" needs at least one later (higher sequence) installment of the document still unpaid. */
export function hasLaterUnpaidInstallments(
  sequence: number,
  installments: readonly PurchaseDocumentInstallment[],
): boolean {
  return installments.some((other) => other.sequence > sequence && !isPaid(other))
}

interface PaymentSchemaOptions {
  installmentAmount: number
  /** False when no later unpaid installment exists: "spread" is not allowed. */
  canSpread: boolean
}

/**
 * Payment dialog (spec 0211 D-6): 0 < paid_amount <= the installment amount;
 * a partial amount also needs the residual choice (and its date for a new installment).
 */
export function buildPurchasePaymentSchema(t: TFunction, { installmentAmount, canSpread }: PaymentSchemaOptions) {
  return z
    .object({
      paid_amount: z
        .number(t('purchaseInvoiceInstallments.payment.errors.amountInvalid'))
        .gt(0, t('purchaseInvoiceInstallments.payment.errors.amountPositive'))
        .max(installmentAmount, t('purchaseInvoiceInstallments.payment.errors.amountExceeds')),
      paid_at: z.string().min(1, t('purchaseInvoiceInstallments.payment.errors.dateRequired')),
      financial_account_id: z.number().int().nullable(),
      residual_mode: z.enum(RESIDUAL_MODES).optional(),
      residual_due_date: z.string().optional(),
    })
    .superRefine((values, ctx) => {
      if (!isPartialCollection(values.paid_amount, installmentAmount)) {
        return
      }
      if (values.residual_mode === undefined) {
        ctx.addIssue({
          code: 'custom',
          path: ['residual_mode'],
          message: t('purchaseInvoiceInstallments.payment.errors.residualModeRequired'),
        })
      } else if (values.residual_mode === 'spread' && !canSpread) {
        ctx.addIssue({
          code: 'custom',
          path: ['residual_mode'],
          message: t('purchaseInvoiceInstallments.payment.errors.noLaterInstallments'),
        })
      } else if (values.residual_mode === 'new_installment' && !values.residual_due_date) {
        ctx.addIssue({
          code: 'custom',
          path: ['residual_due_date'],
          message: t('purchaseInvoiceInstallments.payment.errors.residualDateRequired'),
        })
      }
    })
}

export type PurchasePaymentFormValues = z.infer<ReturnType<typeof buildPurchasePaymentSchema>>

/** Sends `residual_*` only for a partial payment; the date only for a new residual installment. */
export function buildPurchasePaymentPayload(
  values: PurchasePaymentFormValues,
  installmentAmount: number,
): PurchasePaymentPayload {
  const base = {
    paid_amount: values.paid_amount,
    paid_at: values.paid_at,
    financial_account_id: values.financial_account_id,
  }
  if (!isPartialCollection(values.paid_amount, installmentAmount) || values.residual_mode === undefined) {
    return base
  }
  return values.residual_mode === 'new_installment'
    ? { ...base, residual_mode: 'new_installment', residual_due_date: values.residual_due_date }
    : { ...base, residual_mode: 'spread' }
}
