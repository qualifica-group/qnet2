import axios from 'axios'
import type { TFunction } from 'i18next'
import type { ApiErrorResponse } from '@/api/types'
import i18n from '@/i18n'
import { forbiddenMessage } from '@/lib/forbidden-message'
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
 * translated with the skip-reason labels; 409s keep the server message; a 403
 * gets the specific permission sentence.
 */
export function settlementErrorMessage(error: unknown, t: TFunction): string | null {
  const forbidden = forbiddenMessage(error, i18n)
  if (forbidden) {
    return forbidden
  }
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

/** First server validation message of a 422 under `key`, for errors that have no field in the form. */
export function validationMessageFor(error: unknown, key: string): string | null {
  if (!axios.isAxiosError<ApiErrorResponse>(error) || error.response?.status !== UNPROCESSABLE_STATUS) {
    return null
  }
  return error.response.data?.errors?.[key]?.[0] ?? null
}
