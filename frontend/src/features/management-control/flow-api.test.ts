import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import {
  defaultCashFlowFilters,
  defaultTrendFilters,
  fetchCashFlow,
  fetchMonthlyTrend,
  managementControlKeys,
  toCashFlowParams,
  toTrendParams,
} from '@/features/management-control/api'
import { makeCashFlow } from '@/features/management-control/cash-flow-fixtures'
import { makeMonthlyTrend } from '@/features/management-control/trend-fixtures'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const getMock = vi.mocked(apiClient.get)

beforeEach(() => {
  getMock.mockReset()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('cash flow api helpers', () => {
  it('AC-012 defaults to all document types, 6 months and empty arrays', () => {
    expect(defaultCashFlowFilters()).toEqual({
      companyIds: [],
      companySiteIds: [],
      operationalSiteIds: [],
      documentType: 'all',
      horizonMonths: 6,
    })
  })

  it('AC-012 maps the filters to the contract keys and omits empty arrays', () => {
    expect(toCashFlowParams(defaultCashFlowFilters())).toEqual({ document_type: 'all', horizon_months: 6 })

    expect(
      toCashFlowParams({
        ...defaultCashFlowFilters(),
        companyIds: [1, 2],
        companySiteIds: [3],
        operationalSiteIds: [4],
        documentType: 'invoice',
        horizonMonths: 24,
      }),
    ).toEqual({
      company_id: [1, 2],
      company_site_id: [3],
      operational_site_id: [4],
      document_type: 'invoice',
      horizon_months: 24,
    })
  })

  it('AC-012 puts the params in the query key', () => {
    const base = { document_type: 'all' as const, horizon_months: 6 as const }
    expect(managementControlKeys.cashFlow(base)).not.toEqual(managementControlKeys.cashFlow({ ...base, horizon_months: 12 }))
  })

  it('AC-012 fetchCashFlow reads data.data and serializes arrays without indexes', async () => {
    const payload = makeCashFlow()
    getMock.mockResolvedValue({ data: { success: true, message: 'OK', data: payload } })
    const params = { company_id: [1, 2], document_type: 'all' as const, horizon_months: 6 as const }

    await expect(fetchCashFlow(params)).resolves.toBe(payload)
    expect(getMock).toHaveBeenCalledWith('/management-control/cash-flow', { params, paramsSerializer: { indexes: false } })
  })
})

describe('monthly trend api helpers', () => {
  it('AC-012 defaults to the first day of the month 11 months ago, up to today', () => {
    vi.useFakeTimers({ now: new Date(2026, 9, 16, 12) })

    expect(defaultTrendFilters()).toEqual({
      dateFrom: '2025-11-01',
      dateTo: '2026-10-16',
      companyIds: [],
      companySiteIds: [],
      operationalSiteIds: [],
    })
  })

  it('AC-012 the default window crosses the year boundary correctly', () => {
    vi.useFakeTimers({ now: new Date(2026, 11, 31, 12) })
    expect(defaultTrendFilters().dateFrom).toBe('2026-01-01')

    vi.setSystemTime(new Date(2026, 0, 5, 12))
    expect(defaultTrendFilters().dateFrom).toBe('2025-02-01')
  })

  it('AC-012 maps the filters to the contract keys and omits empty values', () => {
    expect(toTrendParams({ ...defaultTrendFilters(), dateFrom: '', dateTo: '' })).toEqual({})

    expect(
      toTrendParams({
        dateFrom: '2026-01-01',
        dateTo: '2026-06-30',
        companyIds: [1],
        companySiteIds: [],
        operationalSiteIds: [5],
      }),
    ).toEqual({ date_from: '2026-01-01', date_to: '2026-06-30', company_id: [1], operational_site_id: [5] })
  })

  it('AC-012 puts the params in the query key', () => {
    expect(managementControlKeys.monthlyTrend({ date_from: '2026-01-01' })).not.toEqual(
      managementControlKeys.monthlyTrend({ date_from: '2026-02-01' }),
    )
  })

  it('AC-012 fetchMonthlyTrend reads data.data and serializes arrays without indexes', async () => {
    const payload = makeMonthlyTrend()
    getMock.mockResolvedValue({ data: { success: true, message: 'OK', data: payload } })
    const params = { date_from: '2026-08-01', date_to: '2026-10-31', company_id: [1, 2] }

    await expect(fetchMonthlyTrend(params)).resolves.toBe(payload)
    expect(getMock).toHaveBeenCalledWith('/management-control/monthly-trend', {
      params,
      paramsSerializer: { indexes: false },
    })
  })
})
