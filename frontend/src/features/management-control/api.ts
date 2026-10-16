import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { todayIso } from '@/features/invoices/invoice-format'
import type { AccountFlows, AccountFlowsFilters, AccountFlowsParams } from '@/features/management-control/accounts-types'
import type { Billing, BillingFilters, BillingParams } from '@/features/management-control/billing-types'
import type { CashFlow, CashFlowFilters, CashFlowParams } from '@/features/management-control/cash-flow-types'
import type { Exposure, ExposureFilters, ExposureParams } from '@/features/management-control/exposure-types'
import type { MonthlyTrend, TrendFilters, TrendParams } from '@/features/management-control/trend-types'
import type { IncomeStatement, IncomeStatementFilters, IncomeStatementParams, ScopeSelection, StructureCosts, StructureCostsParams, StructureGroupBy } from '@/features/management-control/types'

/** Centralized query keys; the params are part of the key, so a change refetches. */
export const managementControlKeys = {
  all: ['management-control'] as const,
  incomeStatement: (params: IncomeStatementParams) => ['management-control', 'income-statement', params] as const,
  structureCosts: (params: StructureCostsParams) => ['management-control', 'structure-costs', params] as const,
  exposure: (params: ExposureParams) => ['management-control', 'exposure', params] as const,
  cashFlow: (params: CashFlowParams) => ['management-control', 'cash-flow', params] as const,
  monthlyTrend: (params: TrendParams) => ['management-control', 'monthly-trend', params] as const,
  billing: (params: BillingParams) => ['management-control', 'billing', params] as const,
  accountFlows: (params: AccountFlowsParams) => ['management-control', 'account-flows', params] as const,
}

/** Default filters: 1 January of the current year to today, contractual revenue, no segmentation. */
export function defaultIncomeStatementFilters(): IncomeStatementFilters {
  const today = todayIso()
  return {
    dateFrom: `${today.slice(0, 4)}-01-01`,
    dateTo: today,
    companyIds: [],
    companySiteIds: [],
    operationalSiteIds: [],
    businessFunctionIds: [],
    workOrderTypes: [],
    commercialIds: [],
    revenueBasis: 'contract',
    segmentBy: 'none',
  }
}

/** Maps the UI filters to the contract query parameters; empty values are omitted. */
export function toIncomeStatementParams(filters: IncomeStatementFilters): IncomeStatementParams {
  return {
    ...(filters.dateFrom ? { date_from: filters.dateFrom } : {}),
    ...(filters.dateTo ? { date_to: filters.dateTo } : {}),
    ...(filters.companyIds.length > 0 ? { company_id: filters.companyIds } : {}),
    ...(filters.companySiteIds.length > 0 ? { company_site_id: filters.companySiteIds } : {}),
    ...(filters.operationalSiteIds.length > 0 ? { operational_site_id: filters.operationalSiteIds } : {}),
    ...(filters.businessFunctionIds.length > 0 ? { business_function_id: filters.businessFunctionIds } : {}),
    ...(filters.workOrderTypes.length > 0 ? { work_order_type: filters.workOrderTypes } : {}),
    ...(filters.commercialIds.length > 0 ? { commercial_id: filters.commercialIds } : {}),
    revenue_basis: filters.revenueBasis,
    segment_by: filters.segmentBy,
  }
}

/** Structure costs share period, company and sites with the page filters; the grouping is their own. */
export function toStructureCostsParams(filters: IncomeStatementFilters, groupBy: StructureGroupBy): StructureCostsParams {
  return {
    ...(filters.dateFrom ? { date_from: filters.dateFrom } : {}),
    ...(filters.dateTo ? { date_to: filters.dateTo } : {}),
    ...(filters.companyIds.length > 0 ? { company_id: filters.companyIds } : {}),
    ...(filters.companySiteIds.length > 0 ? { company_site_id: filters.companySiteIds } : {}),
    ...(filters.operationalSiteIds.length > 0 ? { operational_site_id: filters.operationalSiteIds } : {}),
    group_by: groupBy,
  }
}

/** Default filters: 1 January of the current year to today, invoices only, by company, top 10. */
export function defaultExposureFilters(): ExposureFilters {
  const today = todayIso()
  return {
    dateFrom: `${today.slice(0, 4)}-01-01`,
    dateTo: today,
    companyIds: [],
    companySiteIds: [],
    operationalSiteIds: [],
    documentType: 'invoice',
    groupBy: 'company',
    top: 10,
  }
}

/** Maps the UI filters to the contract query parameters; empty values are omitted. */
export function toExposureParams(filters: ExposureFilters): ExposureParams {
  return {
    ...(filters.dateFrom ? { date_from: filters.dateFrom } : {}),
    ...(filters.dateTo ? { date_to: filters.dateTo } : {}),
    ...(filters.companyIds.length > 0 ? { company_id: filters.companyIds } : {}),
    ...(filters.companySiteIds.length > 0 ? { company_site_id: filters.companySiteIds } : {}),
    ...(filters.operationalSiteIds.length > 0 ? { operational_site_id: filters.operationalSiteIds } : {}),
    document_type: filters.documentType,
    group_by: filters.groupBy,
    top: filters.top,
  }
}

/** Default horizon of the cash flow, in months from the current one. */
const DEFAULT_CASH_FLOW_HORIZON = 6

/** The default trend window is the current month plus this many months before it. */
const TREND_MONTHS_BACK = 11

/** Company and site arrays as contract keys; empty arrays are omitted. */
function scopeParams(filters: ScopeSelection) {
  return {
    ...(filters.companyIds.length > 0 ? { company_id: filters.companyIds } : {}),
    ...(filters.companySiteIds.length > 0 ? { company_site_id: filters.companySiteIds } : {}),
    ...(filters.operationalSiteIds.length > 0 ? { operational_site_id: filters.operationalSiteIds } : {}),
  }
}

/** Default filters: all document types, 6 months of horizon, no scope. */
export function defaultCashFlowFilters(): CashFlowFilters {
  return {
    companyIds: [],
    companySiteIds: [],
    operationalSiteIds: [],
    documentType: 'all',
    horizonMonths: DEFAULT_CASH_FLOW_HORIZON,
  }
}

/** Maps the UI filters to the contract query parameters; empty arrays are omitted. */
export function toCashFlowParams(filters: CashFlowFilters): CashFlowParams {
  return {
    ...scopeParams(filters),
    document_type: filters.documentType,
    horizon_months: filters.horizonMonths,
  }
}

/** Default filters: first day of the month 11 months ago to today (12 months), no scope. */
export function defaultTrendFilters(): TrendFilters {
  const today = todayIso()
  const [year, month] = today.split('-').map(Number)
  const monthIndex = year * 12 + (month - 1) - TREND_MONTHS_BACK
  const fromYear = Math.floor(monthIndex / 12)
  const fromMonth = (monthIndex % 12) + 1
  return {
    dateFrom: `${fromYear}-${String(fromMonth).padStart(2, '0')}-01`,
    dateTo: today,
    companyIds: [],
    companySiteIds: [],
    operationalSiteIds: [],
  }
}

/** Maps the UI filters to the contract query parameters; empty values are omitted. */
export function toTrendParams(filters: TrendFilters): TrendParams {
  return {
    ...(filters.dateFrom ? { date_from: filters.dateFrom } : {}),
    ...(filters.dateTo ? { date_to: filters.dateTo } : {}),
    ...scopeParams(filters),
  }
}

/** Default filters: 1 January of the current year to today (proforma cycle), backlog by business function, no scope. */
export function defaultBillingFilters(): BillingFilters {
  const today = todayIso()
  return {
    dateFrom: `${today.slice(0, 4)}-01-01`,
    dateTo: today,
    companyIds: [],
    companySiteIds: [],
    operationalSiteIds: [],
    groupBy: 'business_function',
  }
}

/** Maps the UI filters to the contract query parameters; empty values are omitted. */
export function toBillingParams(filters: BillingFilters): BillingParams {
  return {
    ...(filters.dateFrom ? { date_from: filters.dateFrom } : {}),
    ...(filters.dateTo ? { date_to: filters.dateTo } : {}),
    ...scopeParams(filters),
    group_by: filters.groupBy,
  }
}

/** Default filters: 1 January of the current year to today, no scope. */
export function defaultAccountFlowsFilters(): AccountFlowsFilters {
  const today = todayIso()
  return {
    dateFrom: `${today.slice(0, 4)}-01-01`,
    dateTo: today,
    companyIds: [],
    companySiteIds: [],
    operationalSiteIds: [],
  }
}

/** Maps the UI filters to the contract query parameters; empty values are omitted. */
export function toAccountFlowsParams(filters: AccountFlowsFilters): AccountFlowsParams {
  return {
    ...(filters.dateFrom ? { date_from: filters.dateFrom } : {}),
    ...(filters.dateTo ? { date_to: filters.dateTo } : {}),
    ...scopeParams(filters),
  }
}

/** Arrays go out as repeated `key[]=` (Laravel convention). */
const ARRAY_SERIALIZER = { indexes: false } as const

export async function fetchIncomeStatement(params: IncomeStatementParams): Promise<IncomeStatement> {
  const { data } = await apiClient.get<ApiResponse<IncomeStatement>>('/management-control/income-statement', {
    params,
    paramsSerializer: ARRAY_SERIALIZER,
  })
  return data.data
}

export async function fetchStructureCosts(params: StructureCostsParams): Promise<StructureCosts> {
  const { data } = await apiClient.get<ApiResponse<StructureCosts>>('/management-control/structure-costs', {
    params,
    paramsSerializer: ARRAY_SERIALIZER,
  })
  return data.data
}

export async function fetchExposure(params: ExposureParams): Promise<Exposure> {
  const { data } = await apiClient.get<ApiResponse<Exposure>>('/management-control/exposure', {
    params,
    paramsSerializer: ARRAY_SERIALIZER,
  })
  return data.data
}

export async function fetchCashFlow(params: CashFlowParams): Promise<CashFlow> {
  const { data } = await apiClient.get<ApiResponse<CashFlow>>('/management-control/cash-flow', {
    params,
    paramsSerializer: ARRAY_SERIALIZER,
  })
  return data.data
}

export async function fetchMonthlyTrend(params: TrendParams): Promise<MonthlyTrend> {
  const { data } = await apiClient.get<ApiResponse<MonthlyTrend>>('/management-control/monthly-trend', {
    params,
    paramsSerializer: ARRAY_SERIALIZER,
  })
  return data.data
}

export async function fetchBilling(params: BillingParams): Promise<Billing> {
  const { data } = await apiClient.get<ApiResponse<Billing>>('/management-control/billing', {
    params,
    paramsSerializer: ARRAY_SERIALIZER,
  })
  return data.data
}

export async function fetchAccountFlows(params: AccountFlowsParams): Promise<AccountFlows> {
  const { data } = await apiClient.get<ApiResponse<AccountFlows>>('/management-control/account-flows', {
    params,
    paramsSerializer: ARRAY_SERIALIZER,
  })
  return data.data
}
