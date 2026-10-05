import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  FinancialAccountDetail,
  FinancialAccountDetailWithPermissions,
  FinancialAccountPayload,
} from '@/features/financial-accounts/types'

/** Fetches one financial account together with the actor's authorization metadata. */
export async function fetchFinancialAccount(
  id: number,
): Promise<FinancialAccountDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<FinancialAccountDetail, ResourcePermissions>
  >(`/financial-accounts/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a financial account of the payload's type. */
export async function createFinancialAccount(
  payload: FinancialAccountPayload,
): Promise<FinancialAccountDetail> {
  const { data } = await apiClient.post<ApiResponse<FinancialAccountDetail>>(
    '/financial-accounts',
    payload,
  )
  return data.data
}

/** Updates a financial account (PATCH); the `type` is the current one, never a change. */
export async function updateFinancialAccount(
  id: number,
  payload: FinancialAccountPayload,
): Promise<FinancialAccountDetail> {
  const { data } = await apiClient.patch<ApiResponse<FinancialAccountDetail>>(
    `/financial-accounts/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a financial account. 204 without body, or 409 when a bank account
 * still has linked cards: the 409 branch is handled by the caller, which shows
 * the backend message.
 */
export async function deleteFinancialAccount(id: number): Promise<void> {
  await apiClient.delete(`/financial-accounts/${id}`)
}

/** Returns the full card number (requires `financial-accounts.revealCardNumber`; audited server-side). */
export async function revealCardNumber(id: number): Promise<string> {
  const { data } = await apiClient.get<ApiResponse<{ card_number: string }>>(
    `/financial-accounts/${id}/card-number`,
  )
  return data.data.card_number
}
