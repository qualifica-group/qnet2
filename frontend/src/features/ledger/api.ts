import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { todayIso } from '@/features/invoices/invoice-format'
import type { Ledger, LedgerFilters, LedgerParams } from '@/features/ledger/types'

/** Centralized query keys; the filters are part of the key, so a change refetches. */
export const ledgerKeys = {
  all: ['ledger'] as const,
  detail: (registryId: number, params: LedgerParams) => ['ledger', registryId, params] as const,
}

/** Default filters: invoices only, from 1 January of the current year to today (D-2, D-3). */
export function defaultLedgerFilters(): LedgerFilters {
  const today = todayIso()
  return {
    documentType: 'invoice',
    dateFrom: `${today.slice(0, 4)}-01-01`,
    dateTo: today,
    companyIds: [],
    companySiteIds: [],
    operationalSiteIds: [],
  }
}

/** Maps the UI filters to the contract's query parameters; empty values are omitted. */
export function toLedgerParams(filters: LedgerFilters): LedgerParams {
  return {
    document_type: filters.documentType,
    ...(filters.dateFrom ? { date_from: filters.dateFrom } : {}),
    ...(filters.dateTo ? { date_to: filters.dateTo } : {}),
    ...(filters.companyIds.length > 0 ? { company_id: filters.companyIds } : {}),
    ...(filters.companySiteIds.length > 0 ? { company_site_id: filters.companySiteIds } : {}),
    ...(filters.operationalSiteIds.length > 0 ? { operational_site_id: filters.operationalSiteIds } : {}),
  }
}

/** Reads the ledger of one registry; arrays go out as repeated `key[]=` (Laravel convention). */
export async function fetchLedger(registryId: number, params: LedgerParams): Promise<Ledger> {
  const { data } = await apiClient.get<ApiResponse<Ledger>>(`/registries/${registryId}/ledger`, {
    params,
    paramsSerializer: { indexes: false },
  })
  return data.data
}
