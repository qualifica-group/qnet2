import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import {
  defaultIncomeStatementFilters,
  fetchIncomeStatement,
  fetchStructureCosts,
  managementControlKeys,
  toIncomeStatementParams,
  toStructureCostsParams,
} from '@/features/management-control/api'
import { makeIncomeStatement, makeStructureCosts } from '@/features/management-control/management-control-fixtures'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const getMock = vi.mocked(apiClient.get)

beforeEach(() => {
  getMock.mockReset()
})

describe('management control api helpers', () => {
  it('AC-015 defaults to 1 January - today, contract basis and no segmentation', () => {
    const filters = defaultIncomeStatementFilters()
    const year = String(new Date().getFullYear())

    expect(filters.dateFrom).toBe(`${year}-01-01`)
    expect(filters.dateTo.startsWith(year)).toBe(true)
    expect(filters.revenueBasis).toBe('contract')
    expect(filters.segmentBy).toBe('none')
    expect(filters.companyIds).toEqual([])
    expect(filters.companySiteIds).toEqual([])
    expect(filters.operationalSiteIds).toEqual([])
    expect(filters.businessFunctionIds).toEqual([])
    expect(filters.workOrderTypes).toEqual([])
    expect(filters.commercialIds).toEqual([])
  })

  it('AC-015 omits empty filters and keeps arrays as contract keys', () => {
    expect(toIncomeStatementParams({ ...defaultIncomeStatementFilters(), dateFrom: '', dateTo: '' })).toEqual({
      revenue_basis: 'contract',
      segment_by: 'none',
    })

    const params = toIncomeStatementParams({
      ...defaultIncomeStatementFilters(),
      dateFrom: '2026-01-01',
      dateTo: '',
      companyIds: [1, 2],
      businessFunctionIds: [4],
      workOrderTypes: ['project'],
      commercialIds: [7],
      revenueBasis: 'collected',
      segmentBy: 'commercial',
    })

    expect(params).toEqual({
      date_from: '2026-01-01',
      company_id: [1, 2],
      business_function_id: [4],
      work_order_type: ['project'],
      commercial_id: [7],
      revenue_basis: 'collected',
      segment_by: 'commercial',
    })
  })

  it('AC-015 maps the shared filters to structure-cost parameters with their own grouping', () => {
    const params = toStructureCostsParams(
      { ...defaultIncomeStatementFilters(), dateFrom: '2026-01-01', dateTo: '2026-06-30', operationalSiteIds: [3], businessFunctionIds: [4] },
      'operational_site',
    )

    expect(params).toEqual({ date_from: '2026-01-01', date_to: '2026-06-30', operational_site_id: [3], group_by: 'operational_site' })
  })

  it('AC-015 puts the params in the query keys', () => {
    const base = { revenue_basis: 'contract' as const, segment_by: 'none' as const }
    expect(managementControlKeys.incomeStatement(base)).not.toEqual(
      managementControlKeys.incomeStatement({ ...base, company_id: [1] }),
    )
    expect(managementControlKeys.structureCosts({ group_by: 'company' })).not.toEqual(
      managementControlKeys.structureCosts({ group_by: 'company_site' }),
    )
  })

  it('AC-015 fetchIncomeStatement reads data.data and serializes arrays without indexes', async () => {
    const payload = makeIncomeStatement()
    getMock.mockResolvedValue({ data: { success: true, message: 'OK', data: payload } })
    const params = { company_id: [1, 2], revenue_basis: 'contract' as const, segment_by: 'company' as const }

    await expect(fetchIncomeStatement(params)).resolves.toBe(payload)
    expect(getMock).toHaveBeenCalledWith('/management-control/income-statement', {
      params,
      paramsSerializer: { indexes: false },
    })
  })

  it('AC-015 fetchStructureCosts reads data.data', async () => {
    const payload = makeStructureCosts()
    getMock.mockResolvedValue({ data: { success: true, message: 'OK', data: payload } })

    await expect(fetchStructureCosts({ group_by: 'company' })).resolves.toBe(payload)
    expect(getMock).toHaveBeenCalledWith('/management-control/structure-costs', {
      params: { group_by: 'company' },
      paramsSerializer: { indexes: false },
    })
  })
})
