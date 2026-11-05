import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { fetchForSelect } from '@/features/for-select/api'
import { fetchRevenueTypesForSelect } from '@/features/revenue-types/api'
import type {
  SubAccountKind,
  SubAccountOption,
  TreasuryMovement,
  TreasuryMovementPayload,
} from '@/features/treasury-movements/types'

/** Page size of the sub-account lists: the chart is small, the select shows it whole. */
const SUB_ACCOUNT_LIMIT = 100

/** Coded sub-account row of `/treasury-types/for-select`: the same shape as the revenue one (spec 0360). */
interface TreasuryTypeForSelectData {
  items: Array<{ id: number; code: string; name: string }>
}

async function fetchTreasuryTypesForSelect(): Promise<TreasuryTypeForSelectData> {
  const { data } = await apiClient.get<ApiResponse<TreasuryTypeForSelectData> | TreasuryTypeForSelectData>(
    '/treasury-types/for-select',
    { params: { offset: 0, limit: SUB_ACCOUNT_LIMIT } },
  )
  return 'items' in data ? data : data.data
}

export async function fetchTreasuryMovement(id: number): Promise<TreasuryMovement> {
  const { data } = await apiClient.get<ApiResponse<TreasuryMovement>>(`/treasury-movements/${id}`)
  return data.data
}

export async function createTreasuryMovement(payload: TreasuryMovementPayload): Promise<TreasuryMovement> {
  const { data } = await apiClient.post<ApiResponse<TreasuryMovement>>('/treasury-movements', payload)
  return data.data
}

export async function updateTreasuryMovement(
  id: number,
  payload: TreasuryMovementPayload,
): Promise<TreasuryMovement> {
  const { data } = await apiClient.patch<ApiResponse<TreasuryMovement>>(`/treasury-movements/${id}`, payload)
  return data.data
}

export async function deleteTreasuryMovement(id: number): Promise<void> {
  await apiClient.delete(`/treasury-movements/${id}`)
}

/** Active sub-accounts of one kind, from the existing for-select endpoints (cost, revenue, financial). */
export async function fetchSubAccountOptions(kind: SubAccountKind): Promise<SubAccountOption[]> {
  if (kind === 'revenue' || kind === 'financial') {
    const { items } = kind === 'revenue'
      ? await fetchRevenueTypesForSelect({ limit: SUB_ACCOUNT_LIMIT })
      : await fetchTreasuryTypesForSelect()
    return items.map((item) => ({ kind, id: item.id, label: `${item.code} ${item.name}` }))
  }
  const { items } = await fetchForSelect<{ id: number; label: string; code?: string | null }>(
    'cost-types',
    { limit: SUB_ACCOUNT_LIMIT },
  )
  return items.map((item) => ({
    kind,
    id: item.id,
    label: item.code ? `${item.code} ${item.label}` : item.label,
  }))
}
