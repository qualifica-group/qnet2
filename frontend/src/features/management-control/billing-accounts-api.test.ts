import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import {
  defaultAccountFlowsFilters,
  defaultBillingFilters,
  fetchAccountFlows,
  fetchBilling,
  managementControlKeys,
  toAccountFlowsParams,
  toBillingParams,
} from '@/features/management-control/api'
import { makeAccountFlows } from '@/features/management-control/accounts-fixtures'
import { makeBilling } from '@/features/management-control/billing-fixtures'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const getMock = vi.mocked(apiClient.get)

beforeEach(() => {
  getMock.mockReset()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('billing api helpers', () => {
  it('AC-010 defaults to 1 January of the current year up to today, by business function, no scope', () => {
    vi.useFakeTimers({ now: new Date(2026, 9, 16, 12) })

    expect(defaultBillingFilters()).toEqual({
      dateFrom: '2026-01-01',
      dateTo: '2026-10-16',
      companyIds: [],
      companySiteIds: [],
      operationalSiteIds: [],
      groupBy: 'business_function',
    })
  })

  it('AC-010 maps the filters to the contract keys and omits empty values', () => {
    expect(toBillingParams({ ...defaultBillingFilters(), dateFrom: '', dateTo: '' })).toEqual({ group_by: 'business_function' })

    expect(
      toBillingParams({
        dateFrom: '2026-01-01',
        dateTo: '2026-06-30',
        companyIds: [1, 2],
        companySiteIds: [3],
        operationalSiteIds: [4],
        groupBy: 'commercial',
      }),
    ).toEqual({
      date_from: '2026-01-01',
      date_to: '2026-06-30',
      company_id: [1, 2],
      company_site_id: [3],
      operational_site_id: [4],
      group_by: 'commercial',
    })
  })

  it('AC-010 puts the params in the query key', () => {
    expect(managementControlKeys.billing({ group_by: 'company' })).not.toEqual(managementControlKeys.billing({ group_by: 'commercial' }))
  })

  it('AC-010 fetchBilling reads data.data and serializes arrays without indexes', async () => {
    const payload = makeBilling()
    getMock.mockResolvedValue({ data: { success: true, message: 'OK', data: payload } })
    const params = { company_id: [1, 2], group_by: 'company' as const }

    await expect(fetchBilling(params)).resolves.toBe(payload)
    expect(getMock).toHaveBeenCalledWith('/management-control/billing', { params, paramsSerializer: { indexes: false } })
  })
})

describe('account flows api helpers', () => {
  it('AC-010 defaults to 1 January of the current year up to today, no scope', () => {
    vi.useFakeTimers({ now: new Date(2026, 9, 16, 12) })

    expect(defaultAccountFlowsFilters()).toEqual({
      dateFrom: '2026-01-01',
      dateTo: '2026-10-16',
      companyIds: [],
      companySiteIds: [],
      operationalSiteIds: [],
    })
  })

  it('AC-010 maps the filters to the contract keys and omits empty values', () => {
    expect(toAccountFlowsParams({ ...defaultAccountFlowsFilters(), dateFrom: '', dateTo: '' })).toEqual({})

    expect(
      toAccountFlowsParams({
        dateFrom: '2026-01-01',
        dateTo: '2026-06-30',
        companyIds: [1],
        companySiteIds: [],
        operationalSiteIds: [5],
      }),
    ).toEqual({ date_from: '2026-01-01', date_to: '2026-06-30', company_id: [1], operational_site_id: [5] })
  })

  it('AC-010 puts the params in the query key', () => {
    expect(managementControlKeys.accountFlows({ date_from: '2026-01-01' })).not.toEqual(
      managementControlKeys.accountFlows({ date_from: '2026-02-01' }),
    )
  })

  it('AC-010 fetchAccountFlows reads data.data and serializes arrays without indexes', async () => {
    const payload = makeAccountFlows()
    getMock.mockResolvedValue({ data: { success: true, message: 'OK', data: payload } })
    const params = { date_from: '2026-08-01', date_to: '2026-10-16', company_id: [1, 2] }

    await expect(fetchAccountFlows(params)).resolves.toBe(payload)
    expect(getMock).toHaveBeenCalledWith('/management-control/account-flows', {
      params,
      paramsSerializer: { indexes: false },
    })
  })
})
