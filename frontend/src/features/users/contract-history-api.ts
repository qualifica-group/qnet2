import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  CreateEmploymentContractPayload,
  EmploymentContract,
} from '@/features/users/contract-history-types'

/** Query key of one user's contract history (spec 0272). */
export function employmentContractsQueryKey(userId: number) {
  return ['users', 'employment-contracts', userId] as const
}

export async function fetchEmploymentContracts(userId: number): Promise<EmploymentContract[]> {
  const { data } = await apiClient.get<ApiResponse<EmploymentContract[]>>(`/users/${userId}/employment-contracts`)
  return data.data
}

export async function createEmploymentContract(
  userId: number,
  payload: CreateEmploymentContractPayload,
): Promise<EmploymentContract> {
  const { data } = await apiClient.post<ApiResponse<EmploymentContract>>(
    `/users/${userId}/employment-contracts`,
    payload,
  )
  return data.data
}

/** Deletes a condition; the server accepts only the latest one (409 otherwise). */
export async function deleteEmploymentContract(userId: number, contractId: number): Promise<void> {
  await apiClient.delete(`/users/${userId}/employment-contracts/${contractId}`)
}
