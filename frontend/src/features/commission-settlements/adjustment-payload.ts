import { todayIso } from '@/features/invoices/invoice-format'
import type { AdjustmentFormValues, AdjustmentSchemaOutput } from '@/features/commission-settlements/adjustment-schema'
import type {
  AdjustmentCreatePayload,
  AdjustmentMode,
  AdjustmentUpdatePayload,
} from '@/features/commission-settlements/types'

export function buildAdjustmentDefaults(mode: AdjustmentMode): AdjustmentFormValues {
  switch (mode.type) {
    case 'edit':
      return { referent_id: null, company_id: null, amount: mode.amount, accrued_on: mode.accruedOn, notes: mode.notes }
    case 'draft':
      return { referent_id: mode.referent.id, company_id: mode.company.id, amount: '', accrued_on: todayIso(), notes: '' }
    case 'standalone':
      return { referent_id: null, company_id: null, amount: '', accrued_on: todayIso(), notes: '' }
  }
}

/** Creation body: inside a draft the agent/company are the draft ones and its id travels along. */
export function buildAdjustmentCreatePayload(
  values: AdjustmentSchemaOutput,
  settlementId: number | null,
): AdjustmentCreatePayload {
  return {
    referent_id: Number(values.referent_id),
    company_id: Number(values.company_id),
    amount: Number(values.amount),
    accrued_on: values.accrued_on,
    notes: values.notes,
    commission_settlement_id: settlementId,
  }
}

export function buildAdjustmentUpdatePayload(values: AdjustmentSchemaOutput): AdjustmentUpdatePayload {
  return { amount: Number(values.amount), accrued_on: values.accrued_on, notes: values.notes }
}
