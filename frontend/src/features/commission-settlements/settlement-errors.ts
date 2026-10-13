import axios from 'axios'
import type { TFunction } from 'i18next'
import type { ApiErrorResponse } from '@/api/types'
import { SETTLEMENT_SKIP_REASONS, type SettlementSkipReason } from '@/features/commission-settlement-runs/types'

const CONFLICT_STATUS = 409
const UNPROCESSABLE_STATUS = 422

function isSkipReason(code: string): code is SettlementSkipReason {
  return (SETTLEMENT_SKIP_REASONS as readonly string[]).includes(code)
}

/**
 * Message for a failed settlement transition, or `null` when the failure
 * carries none (the caller falls back to a generic text). A 422 `reason` that
 * is a machine code (missing_profile, missing_enasarco_parameters) is
 * translated with the skip-reason labels; 409s keep the server message.
 */
export function settlementErrorMessage(error: unknown, t: TFunction): string | null {
  if (!axios.isAxiosError<ApiErrorResponse>(error)) {
    return null
  }
  const status = error.response?.status
  if (status !== CONFLICT_STATUS && status !== UNPROCESSABLE_STATUS) {
    return null
  }
  const data = error.response?.data
  const reason = data?.errors?.reason?.[0]
  if (reason !== undefined && isSkipReason(reason)) {
    return t(`commissionSettlementRuns.skipReasons.${reason}`)
  }
  return reason ?? data?.message ?? null
}
