import { z } from 'zod'
import type { TFunction } from 'i18next'
import { isPartialCollection } from '@/features/invoices/invoice-collection-residual'
import { RESIDUAL_MODES } from '@/features/invoices/types'
import {
  buildWriteOffShape,
  effectiveResidualMode,
  isZeroAmount,
  writeOffPayloadFields,
} from '@/features/invoices/write-off'
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
  /** Residual (euros) up to which write-off is the proposed choice (spec 0361, D-5). */
  writeOffThreshold: number
}

/**
 * Payment dialog (spec 0211 D-6, 0361): 0 <= paid_amount <= the installment amount, where 0 is only valid as a
 * total write-off; a partial amount also needs the residual choice (and its date for a new installment).
 */
export function buildPurchasePaymentSchema(
  t: TFunction,
  { installmentAmount, canSpread, writeOffThreshold }: PaymentSchemaOptions,
) {
  return z
    .object({
      paid_amount: z
        .number(t('purchaseInvoiceInstallments.payment.errors.amountInvalid'))
        .gte(0, t('purchaseInvoiceInstallments.payment.errors.amountPositive'))
        .max(installmentAmount, t('purchaseInvoiceInstallments.payment.errors.amountExceeds')),
      paid_at: z.string().min(1, t('purchaseInvoiceInstallments.payment.errors.dateRequired')),
      financial_account_id: z.number().int().nullable(),
      residual_mode: z.enum(RESIDUAL_MODES).optional(),
      residual_due_date: z.string().optional(),
      ...buildWriteOffShape(t),
    })
    .superRefine((values, ctx) => {
      if (!isPartialCollection(values.paid_amount, installmentAmount)) {
        return
      }
      const mode = effectiveResidualMode(values.residual_mode, values.paid_amount, installmentAmount, writeOffThreshold)
      if (isZeroAmount(values.paid_amount) && mode !== 'write_off') {
        ctx.addIssue({ code: 'custom', path: ['paid_amount'], message: t('writeOff.errors.zeroNeedsWriteOff') })
      }
      if (mode === 'spread' && !canSpread) {
        ctx.addIssue({
          code: 'custom',
          path: ['residual_mode'],
          message: t('purchaseInvoiceInstallments.payment.errors.noLaterInstallments'),
        })
      } else if (mode === 'new_installment' && !values.residual_due_date) {
        ctx.addIssue({
          code: 'custom',
          path: ['residual_due_date'],
          message: t('purchaseInvoiceInstallments.payment.errors.residualDateRequired'),
        })
      }
    })
}

export type PurchasePaymentFormValues = z.infer<ReturnType<typeof buildPurchasePaymentSchema>>

/**
 * Sends `residual_*` only for a partial payment (the date only for a new residual installment, the reason and
 * note only for a write-off). A zero amount moves no money, so it never carries an account (spec 0361).
 */
export function buildPurchasePaymentPayload(
  values: PurchasePaymentFormValues,
  installmentAmount: number,
  writeOffThreshold: number,
): PurchasePaymentPayload {
  const base = {
    paid_amount: values.paid_amount,
    paid_at: values.paid_at,
    financial_account_id: isZeroAmount(values.paid_amount) ? null : values.financial_account_id,
  }
  if (!isPartialCollection(values.paid_amount, installmentAmount)) {
    return base
  }
  const mode = effectiveResidualMode(values.residual_mode, values.paid_amount, installmentAmount, writeOffThreshold)
  if (mode === 'write_off') {
    return { ...base, ...writeOffPayloadFields(values) }
  }
  return mode === 'new_installment'
    ? { ...base, residual_mode: 'new_installment', residual_due_date: values.residual_due_date }
    : { ...base, residual_mode: 'spread' }
}
