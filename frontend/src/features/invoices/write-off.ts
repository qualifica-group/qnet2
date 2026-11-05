import { z } from 'zod'
import type { TFunction } from 'i18next'
import { fromCents, toCents } from '@/lib/amounts/line-amounts'
import { WRITE_OFF_NOTE_MAX, WRITE_OFF_REASONS, type ResidualMode, type WriteOffReason } from '@/features/invoices/types'

/** Used only when `GET /api/config` does not carry `treasury.write_off_suggest_threshold`. */
export const DEFAULT_WRITE_OFF_THRESHOLD = '5.00'

export const DEFAULT_WRITE_OFF_REASON: WriteOffReason = 'rounding'

/** Form fields shared by the collection and the payment dialog (spec 0361). */
export interface WriteOffFormFields {
  write_off_reason: WriteOffReason
  write_off_note: string
}

/** Zod shape of the write-off fields; the amount is never part of the form (the server computes it). */
export function buildWriteOffShape(t: TFunction) {
  return {
    write_off_reason: z.enum(WRITE_OFF_REASONS),
    write_off_note: z.string().max(WRITE_OFF_NOTE_MAX, t('writeOff.errors.noteTooLong', { max: WRITE_OFF_NOTE_MAX })),
  }
}

/** Proposed choice for the residual: within the threshold -> write-off, above -> new installment. */
export function proposedResidualMode(
  settledAmount: number,
  installmentAmount: number,
  threshold: number,
): ResidualMode {
  const residualCents = toCents(installmentAmount) - toCents(settledAmount)
  return residualCents <= toCents(threshold) ? 'write_off' : 'new_installment'
}

/** The mode the user picked, or the proposal while they have not chosen. */
export function effectiveResidualMode(
  chosen: ResidualMode | undefined,
  settledAmount: number,
  installmentAmount: number,
  threshold: number,
): ResidualMode {
  return chosen ?? proposedResidualMode(settledAmount, installmentAmount, threshold)
}

/** Amount of the write-off shown read-only: installment amount minus the settled one. */
export function writeOffAmountOf(settledAmount: number, installmentAmount: number): number {
  return fromCents(Math.max(toCents(installmentAmount) - toCents(settledAmount), 0))
}

/** A zero amount is only valid as a total write-off (spec 0361, D-3). */
export function isZeroAmount(amount: number): boolean {
  return Number.isFinite(amount) && toCents(amount) === 0
}

/** Request fields of the write-off choice; the note is sent as null when blank. */
export function writeOffPayloadFields(values: WriteOffFormFields) {
  return {
    residual_mode: 'write_off' as const,
    write_off_reason: values.write_off_reason,
    write_off_note: values.write_off_note.trim() === '' ? null : values.write_off_note.trim(),
  }
}
