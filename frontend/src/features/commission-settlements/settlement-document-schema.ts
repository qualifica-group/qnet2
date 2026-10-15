import { z } from 'zod'
import type { TFunction } from 'i18next'
import { todayIso } from '@/features/invoices/invoice-format'
import type { SettlementDetail, SettlementDocumentPayload } from '@/features/commission-settlements/types'

const AMOUNT_PATTERN = /^\d{1,13}(\.\d{1,2})?$/
const NUMBER_MAX_LENGTH = 30

export const DOCUMENT_AMOUNT_FIELDS = [
  'document_taxable_amount',
  'document_vat_amount',
  'document_withholding_amount',
  'document_enasarco_amount',
  'document_total_amount',
] as const

function toCents(value: string): number {
  return Math.round(Number(value) * 100)
}

/**
 * Zod schema of the "Registra documento" dialog (spec 0267 D-3), a factory so
 * messages are localized: number required (max 30), date not in the future,
 * amounts >= 0 with at most 2 decimals, total exactly taxable + VAT. The
 * tolerance against the statement is checked by the server (422 on the field).
 */
export function buildDocumentSchema(t: TFunction) {
  const amount = z
    .string()
    .trim()
    .min(1, t('commissionSettlements.documentDialog.amountRequired'))
    .refine((value) => AMOUNT_PATTERN.test(value), { message: t('commissionSettlements.documentDialog.amountInvalid') })

  return z
    .object({
      document_number: z
        .string()
        .trim()
        .min(1, t('commissionSettlements.documentDialog.numberRequired'))
        .max(NUMBER_MAX_LENGTH, t('commissionSettlements.documentDialog.numberMax')),
      document_date: z
        .string()
        .min(1, t('commissionSettlements.documentDialog.dateRequired'))
        .refine((value) => value <= todayIso(), { message: t('commissionSettlements.documentDialog.dateFuture') }),
      document_taxable_amount: amount,
      document_vat_amount: amount,
      document_withholding_amount: amount,
      document_enasarco_amount: amount,
      document_total_amount: amount,
    })
    .superRefine((values, context) => {
      const allValid = DOCUMENT_AMOUNT_FIELDS.every((key) => AMOUNT_PATTERN.test(values[key].trim()))
      if (allValid && toCents(values.document_total_amount) !== toCents(values.document_taxable_amount) + toCents(values.document_vat_amount)) {
        context.addIssue({
          code: 'custom',
          path: ['document_total_amount'],
          message: t('commissionSettlements.documentDialog.totalMismatch'),
        })
      }
    })
}

type DocumentSchema = ReturnType<typeof buildDocumentSchema>

export type DocumentFormValues = z.input<DocumentSchema>
export type DocumentSchemaOutput = z.output<DocumentSchema>

function plain(value: string | undefined): string {
  return value === undefined ? '' : Number(value).toFixed(2)
}

/** Prefilled with the statement's own amounts (the agent usually bills exactly the statement). */
export function buildDocumentDefaults(settlement: SettlementDetail): DocumentFormValues {
  const taxable = Number(settlement.taxable_amount ?? 0)
  const vat = Number(settlement.vat_amount ?? 0)
  return {
    document_number: '',
    document_date: todayIso(),
    document_taxable_amount: plain(settlement.taxable_amount),
    document_vat_amount: plain(settlement.vat_amount),
    document_withholding_amount: plain(settlement.withholding_amount),
    document_enasarco_amount: plain(settlement.enasarco_agent_amount),
    document_total_amount: settlement.taxable_amount === undefined ? '' : (taxable + vat).toFixed(2),
  }
}

export function buildDocumentPayload(values: DocumentSchemaOutput): SettlementDocumentPayload {
  return {
    document_number: values.document_number,
    document_date: values.document_date,
    document_taxable_amount: Number(values.document_taxable_amount),
    document_vat_amount: Number(values.document_vat_amount),
    document_withholding_amount: Number(values.document_withholding_amount),
    document_enasarco_amount: Number(values.document_enasarco_amount),
    document_total_amount: Number(values.document_total_amount),
  }
}
