import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { todayIso } from '@/features/invoices/invoice-format'
import { filenameFromContentDisposition } from '@/lib/download'
import type {
  AccountBalances,
  Statement,
  StatementFilters,
  StatementParams,
  UnassignedList,
} from '@/features/account-statements/types'

/** Centralized query keys; the filters are part of the key, so a change refetches. */
export const accountStatementKeys = {
  all: ['account-statements'] as const,
  balances: (companyId: number | null) => ['account-statements', 'balances', companyId] as const,
  unassigned: (companyId: number | null) => ['account-statements', 'unassigned', companyId] as const,
  statement: (accountId: number, params: StatementParams) =>
    ['account-statements', 'statement', accountId, params] as const,
}

const SERIALIZER = { indexes: false } as const
const FALLBACK_EXPORT_NAME = 'account-statement.xlsx'

/** Default period: first day of the current month to today (spec 0360). */
export function defaultStatementFilters(): StatementFilters {
  const today = todayIso()
  return { dateFrom: `${today.slice(0, 8)}01`, dateTo: today, types: [], registryId: null, search: '' }
}

/** Maps the UI filters to the contract's query parameters; empty values are omitted. */
export function toStatementParams(filters: StatementFilters): StatementParams {
  const search = filters.search.trim()
  return {
    date_from: filters.dateFrom,
    date_to: filters.dateTo,
    ...(filters.types.length > 0 ? { types: filters.types } : {}),
    ...(filters.registryId !== null ? { registry_id: filters.registryId } : {}),
    ...(search ? { search } : {}),
  }
}

export async function fetchBalances(companyId: number | null): Promise<AccountBalances> {
  const { data } = await apiClient.get<ApiResponse<AccountBalances>>('/account-statements/balances', {
    params: companyId !== null ? { company_id: companyId } : {},
  })
  return data.data
}

export async function fetchUnassigned(companyId: number | null): Promise<UnassignedList> {
  const { data } = await apiClient.get<ApiResponse<UnassignedList>>('/account-statements/unassigned', {
    params: companyId !== null ? { company_id: companyId } : {},
  })
  return data.data
}

export async function fetchStatement(accountId: number, params: StatementParams): Promise<Statement> {
  const { data } = await apiClient.get<ApiResponse<Statement>>(`/financial-accounts/${accountId}/statement`, {
    params,
    paramsSerializer: SERIALIZER,
  })
  return data.data
}

/** Same filters as the statement; the server answers an xlsx file. */
export async function exportStatement(
  accountId: number,
  params: StatementParams,
): Promise<{ blob: Blob; filename: string }> {
  const response = await apiClient.get<Blob>(`/financial-accounts/${accountId}/statement/export`, {
    params,
    paramsSerializer: SERIALIZER,
    responseType: 'blob',
  })
  return {
    blob: response.data,
    filename: filenameFromContentDisposition(response.headers['content-disposition']) ?? FALLBACK_EXPORT_NAME,
  }
}
