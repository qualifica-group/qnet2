import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { todayIso } from '@/features/invoices/invoice-format'
import { filenameFromContentDisposition } from '@/lib/download'
import type {
  AccountBalances,
  BankReconciliation,
  ReconciledFilter,
  ReconciliationPayload,
  ReconciliationPreview,
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
  reconciliations: (accountId: number) => ['account-statements', 'reconciliations', accountId] as const,
  preview: (accountId: number, date: string) => ['account-statements', 'preview', accountId, date] as const,
}

const SERIALIZER = { indexes: false } as const
const FALLBACK_EXPORT_NAME = 'account-statement.xlsx'

/** Default period: first day of the current month to today (spec 0360). */
export function defaultStatementFilters(reconciled: ReconciledFilter = 'all'): StatementFilters {
  const today = todayIso()
  return { dateFrom: `${today.slice(0, 8)}01`, dateTo: today, types: [], registryId: null, search: '', reconciled }
}

/** `?reconciled=` of the page URL; anything but yes/no means no filter. */
export function parseReconciledParam(raw: string | null): ReconciledFilter {
  return raw === 'yes' || raw === 'no' ? raw : 'all'
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
    ...(filters.reconciled !== 'all' ? { reconciled: filters.reconciled } : {}),
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

/** Max keys per marks request (contract: 1..500). */
export const MARKS_CHUNK_SIZE = 500

/** Ticks or unticks statement rows; a larger selection goes in sequential chunks. Returns the rows changed. */
export async function setReconciliationMarks(accountId: number, keys: string[], reconciled: boolean): Promise<number> {
  let updated = 0
  for (let start = 0; start < keys.length; start += MARKS_CHUNK_SIZE) {
    const { data } = await apiClient.post<ApiResponse<{ updated: number }>>(
      `/financial-accounts/${accountId}/reconciliation-marks`,
      { keys: keys.slice(start, start + MARKS_CHUNK_SIZE), reconciled },
    )
    updated += data.data.updated
  }
  return updated
}

export async function fetchReconciliations(accountId: number): Promise<BankReconciliation[]> {
  const { data } = await apiClient.get<ApiResponse<BankReconciliation[]>>(
    `/financial-accounts/${accountId}/bank-reconciliations`,
  )
  return data.data
}

export async function fetchReconciliationPreview(accountId: number, date: string): Promise<ReconciliationPreview> {
  const { data } = await apiClient.get<ApiResponse<ReconciliationPreview>>(
    `/financial-accounts/${accountId}/bank-reconciliations/preview`,
    { params: { statement_date: date } },
  )
  return data.data
}

export async function createReconciliation(
  accountId: number,
  payload: ReconciliationPayload,
): Promise<BankReconciliation> {
  const { data } = await apiClient.post<ApiResponse<BankReconciliation>>(
    `/financial-accounts/${accountId}/bank-reconciliations`,
    payload,
  )
  return data.data
}

export async function deleteReconciliation(id: number): Promise<void> {
  await apiClient.delete(`/bank-reconciliations/${id}`)
}
