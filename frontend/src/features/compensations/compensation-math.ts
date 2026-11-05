import { z } from 'zod'
import type { TFunction } from 'i18next'
import { todayIso } from '@/features/invoices/invoice-format'
import { COMPENSATION_RESIDUAL_MODES, type CompensationPayload } from '@/features/compensations/types'

/** Form sentinel of an unselected id. */
export const NO_ID = 0
const NOTE_MAX_LENGTH = 255

/** Decimal string "1234.56" to integer cents, without floats in the comparison. */
export function stringToCents(amount: string): number {
  const [whole, fraction = ''] = amount.trim().split('.')
  const cents = Math.abs(Number(whole)) * 100 + Number(fraction.padEnd(2, '0').slice(0, 2))
  return whole.startsWith('-') ? -cents : cents
}

export interface CompensationAmounts {
  /** Minor of the two residuals, in cents; 0 until both installments are chosen. */
  amountCents: number
  /** True when the residuals differ: the user must say what happens to the remainder. */
  residualDiffers: boolean
}

/** Amount of the compensation = the minor residual (spec 0364 D-2). */
export function computeCompensation(receivableResidual: string | null, payableResidual: string | null): CompensationAmounts {
  if (receivableResidual === null || payableResidual === null) {
    return { amountCents: 0, residualDiffers: false }
  }
  const receivable = stringToCents(receivableResidual)
  const payable = stringToCents(payableResidual)
  return { amountCents: Math.min(receivable, payable), residualDiffers: receivable !== payable }
}

/** Form schema of the fields that do not depend on the chosen installments. */
export function buildCompensationSchema(t: TFunction) {
  return z.object({
    company_id: z.number().int().min(1, t('compensations.errors.company')),
    invoice_installment_id: z.number().int().min(1, t('compensations.errors.receivable')),
    purchase_invoice_installment_id: z.number().int().min(1, t('compensations.errors.payable')),
    compensated_at: z
      .string()
      .min(1, t('compensations.errors.date'))
      .refine((value) => value <= todayIso(), t('compensations.errors.dateFuture')),
    note: z.string().max(NOTE_MAX_LENGTH, t('compensations.errors.noteLength')),
    residual_mode: z.enum(COMPENSATION_RESIDUAL_MODES).optional(),
    residual_due_date: z.string().optional(),
  })
}

export type CompensationFormValues = z.infer<ReturnType<typeof buildCompensationSchema>>

/**
 * Rule that depends on the chosen installments (known only after the candidates load): when the residuals differ
 * the user must pick what happens to the remainder, and a new installment needs its date. Returns the field errors.
 */
export function residualErrors(
  t: TFunction,
  values: CompensationFormValues,
  residualDiffers: boolean,
): Partial<Record<'residual_mode' | 'residual_due_date', string>> {
  if (!residualDiffers) {
    return {}
  }
  if (values.residual_mode === undefined) {
    return { residual_mode: t('compensations.errors.residualMode') }
  }
  if (values.residual_mode === 'new_installment' && !values.residual_due_date) {
    return { residual_due_date: t('compensations.errors.residualDate') }
  }
  return {}
}

/** The residual fields travel only when the residuals differ; the date only for a new installment. */
export function buildCompensationPayload(values: CompensationFormValues, residualDiffers: boolean): CompensationPayload {
  const note = values.note.trim()
  return {
    invoice_installment_id: values.invoice_installment_id,
    purchase_invoice_installment_id: values.purchase_invoice_installment_id,
    compensated_at: values.compensated_at,
    note: note === '' ? null : note,
    ...(residualDiffers && values.residual_mode ? { residual_mode: values.residual_mode } : {}),
    ...(residualDiffers && values.residual_mode === 'new_installment' && values.residual_due_date
      ? { residual_due_date: values.residual_due_date }
      : {}),
  }
}
