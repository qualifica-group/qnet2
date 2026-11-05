import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  CompensationCandidates,
  CompensationPayload,
  InstallmentCompensation,
} from '@/features/compensations/types'

/** Centralized query keys; the registry is part of the key, so each anagrafica has its own cache. */
export const compensationKeys = {
  all: ['compensations'] as const,
  candidates: (registryId: number, companyId: number) => ['compensations', 'candidates', registryId, companyId] as const,
  list: (registryId: number) => ['compensations', 'list', registryId] as const,
}

export async function fetchCandidates(registryId: number, companyId: number): Promise<CompensationCandidates> {
  const { data } = await apiClient.get<ApiResponse<CompensationCandidates>>(
    `/registries/${registryId}/compensation-candidates`,
    { params: { company_id: companyId } },
  )
  return data.data
}

export async function fetchCompensations(registryId: number): Promise<InstallmentCompensation[]> {
  const { data } = await apiClient.get<ApiResponse<InstallmentCompensation[]>>(`/registries/${registryId}/compensations`)
  return data.data
}

export async function createCompensation(payload: CompensationPayload): Promise<InstallmentCompensation> {
  const { data } = await apiClient.post<ApiResponse<InstallmentCompensation>>('/installment-compensations', payload)
  return data.data
}

export async function deleteCompensation(id: number): Promise<void> {
  await apiClient.delete(`/installment-compensations/${id}`)
}
