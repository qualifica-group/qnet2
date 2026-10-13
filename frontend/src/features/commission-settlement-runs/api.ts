import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import {
  COMMISSION_SETTLEMENT_RUNS_DOMAIN,
  type CommissionSettlementRunPayload,
  type CommissionSettlementRunResult,
} from '@/features/commission-settlement-runs/types'

const ENDPOINT = `/${COMMISSION_SETTLEMENT_RUNS_DOMAIN}`

/** Centralized query keys of the commission-settlement-runs feature. */
export const commissionSettlementRunKeys = {
  all: [COMMISSION_SETTLEMENT_RUNS_DOMAIN] as const,
  detail: (id: number) => [COMMISSION_SETTLEMENT_RUNS_DOMAIN, 'detail', id] as const,
}

/** Launches a run (synchronous): the response lists the created and the skipped settlements. */
export async function createCommissionSettlementRun(
  payload: CommissionSettlementRunPayload,
): Promise<CommissionSettlementRunResult> {
  const { data } = await apiClient.post<ApiResponse<CommissionSettlementRunResult>>(ENDPOINT, payload)
  return data.data
}

export async function fetchCommissionSettlementRun(id: number): Promise<CommissionSettlementRunResult> {
  const { data } = await apiClient.get<ApiResponse<CommissionSettlementRunResult>>(`${ENDPOINT}/${id}`)
  return data.data
}
