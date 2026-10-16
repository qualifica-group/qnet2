import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { defaultAgentsFilters, fetchAgents, managementControlKeys, toAgentsParams } from '@/features/management-control/api'
import { makeAgents } from '@/features/management-control/agents-fixtures'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const getMock = vi.mocked(apiClient.get)

beforeEach(() => {
  getMock.mockReset()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('agents api helpers', () => {
  it('AC-010 defaults to 1 January of the current year up to today, every company, top 20', () => {
    vi.useFakeTimers({ now: new Date(2026, 9, 16, 12) })

    expect(defaultAgentsFilters()).toEqual({ dateFrom: '2026-01-01', dateTo: '2026-10-16', companyIds: [], top: 20 })
  })

  it('AC-010 maps the filters to the contract parameters, omitting empty values', () => {
    const filters = { dateFrom: '2026-01-01', dateTo: '2026-06-30', companyIds: [], top: 10 as const }

    expect(toAgentsParams(filters)).toEqual({ date_from: '2026-01-01', date_to: '2026-06-30', top: 10 })
    expect(toAgentsParams({ ...filters, dateFrom: '', companyIds: [3, 4], top: 50 })).toEqual({
      date_to: '2026-06-30',
      company_id: [3, 4],
      top: 50,
    })
  })

  it('AC-010 keys the query on the parameters, so a filter change refetches', () => {
    const base = toAgentsParams(defaultAgentsFilters())

    expect(managementControlKeys.agents(base)).not.toEqual(managementControlKeys.agents({ ...base, top: 50 }))
  })

  it('AC-010 fetches the agents endpoint with repeated array keys and returns data.data', async () => {
    const agents = makeAgents()
    getMock.mockResolvedValue({ data: { success: true, message: 'OK', data: agents } })

    const result = await fetchAgents({ date_from: '2026-01-01', company_id: [1, 2], top: 20 })

    expect(result).toBe(agents)
    expect(getMock).toHaveBeenCalledWith('/management-control/agents', {
      params: { date_from: '2026-01-01', company_id: [1, 2], top: 20 },
      paramsSerializer: { indexes: false },
    })
  })
})
