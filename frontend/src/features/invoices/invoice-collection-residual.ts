import { fromCents, toCents } from '@/features/invoices/invoice-amounts'
import type { InvoiceCollectionPayload, InvoiceInstallment, ResidualMode } from '@/features/invoices/types'

/** Days added to the due date of the closed installment to propose the date of the residual one (spec 0196, D-6). */
export const RESIDUAL_INSTALLMENT_DAYS = 30

/** An installment carries a collection when its collected amount is set and not zero. */
export function isCollected(installment: InvoiceInstallment): boolean {
  return installment.collected_amount !== null && Number(installment.collected_amount) > 0
}

/** "Spalma" needs at least one later (higher sequence) installment still open. */
export function hasLaterOpenInstallments(
  installment: InvoiceInstallment,
  installments: readonly InvoiceInstallment[],
): boolean {
  return installments.some((other) => other.sequence > installment.sequence && !isCollected(other))
}

/** `YYYY-MM-DD` plus `days`, computed on the calendar date (no timezone drift). */
export function addDaysIso(isoDate: string, days: number): string {
  const [year, month, day] = isoDate.slice(0, 10).split('-').map(Number)
  const shifted = new Date(Date.UTC(year, month - 1, day + days))
  return shifted.toISOString().slice(0, 10)
}

/** True when the collected amount is strictly lower than the installment amount (cents compared). */
export function isPartialCollection(collectedAmount: number, installmentAmount: number): boolean {
  return Number.isFinite(collectedAmount) && toCents(collectedAmount) < toCents(installmentAmount)
}

/** Amount left on the installment after a partial collection, in euros. */
export function residualAmountOf(collectedAmount: number, installmentAmount: number): number {
  return fromCents(Math.max(toCents(installmentAmount) - toCents(collectedAmount), 0))
}

interface CollectionFormFields {
  collected_amount: number
  collected_at: string
  residual_mode?: ResidualMode
  residual_due_date?: string
}

/** Sends `residual_*` only for a partial collection; the date only for a new residual installment. */
export function buildCollectionPayload(values: CollectionFormFields, installmentAmount: number): InvoiceCollectionPayload {
  const base = { collected_amount: values.collected_amount, collected_at: values.collected_at }
  if (!isPartialCollection(values.collected_amount, installmentAmount) || values.residual_mode === undefined) {
    return base
  }
  return values.residual_mode === 'new_installment'
    ? { ...base, residual_mode: 'new_installment', residual_due_date: values.residual_due_date }
    : { ...base, residual_mode: 'spread' }
}
