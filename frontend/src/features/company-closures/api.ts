import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import {
  COMPANY_CLOSURES_DOMAIN,
  type CompanyClosure,
  type CompanyClosurePayload,
} from '@/features/company-closures/types'

const ENDPOINT = `/${COMPANY_CLOSURES_DOMAIN}`

/** Centralized query keys of the company closures catalogue. */
export const companyClosureKeys = {
  all: ['company-closures'] as const,
  detail: (id: number) => ['company-closures', 'detail', id] as const,
}

export async function fetchCompanyClosure(id: number): Promise<CompanyClosure> {
  const { data } = await apiClient.get<ApiResponse<CompanyClosure>>(`${ENDPOINT}/${id}`)
  return data.data
}

/** The response of a collective leave carries the `generation` summary. */
export async function createCompanyClosure(payload: CompanyClosurePayload): Promise<CompanyClosure> {
  const { data } = await apiClient.post<ApiResponse<CompanyClosure>>(ENDPOINT, payload)
  return data.data
}

/** 409 when the closure already started (and does not repeat yearly). */
export async function updateCompanyClosure(id: number, payload: CompanyClosurePayload): Promise<CompanyClosure> {
  const { data } = await apiClient.put<ApiResponse<CompanyClosure>>(`${ENDPOINT}/${id}`, payload)
  return data.data
}

/** 409 when the closure already started (and does not repeat yearly). */
export async function deleteCompanyClosure(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}
