import type {
  CommissionSettlementRunFormValues,
  CommissionSettlementRunSchemaOutput,
} from '@/features/commission-settlement-runs/commission-settlement-run-schema'
import type { CommissionSettlementRunPayload } from '@/features/commission-settlement-runs/types'

function isoDate(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

/** Default period of a run: the whole previous calendar month (local dates, never the UTC slice). */
export function previousMonthRange(now: Date = new Date()): { from: string; to: string } {
  const first = new Date(now.getFullYear(), now.getMonth() - 1, 1)
  const last = new Date(now.getFullYear(), now.getMonth(), 0)
  return { from: isoDate(first), to: isoDate(last) }
}

export function buildDefaultRunValues(now: Date = new Date()): CommissionSettlementRunFormValues {
  const { from, to } = previousMonthRange(now)
  return {
    period_from: from,
    period_to: to,
    company_id: null,
    referent_ids: [],
    recipient_roles: [],
    min_amount: '',
  }
}

/** Optional filters travel as null when left empty, never as empty arrays. */
export function buildCommissionSettlementRunPayload(
  values: CommissionSettlementRunSchemaOutput,
): CommissionSettlementRunPayload {
  return {
    period_from: values.period_from,
    period_to: values.period_to,
    company_id: values.company_id,
    referent_ids: values.referent_ids.length > 0 ? values.referent_ids : null,
    recipient_roles: values.recipient_roles.length > 0 ? values.recipient_roles : null,
    min_amount: values.min_amount === '' ? null : Number(values.min_amount),
  }
}
