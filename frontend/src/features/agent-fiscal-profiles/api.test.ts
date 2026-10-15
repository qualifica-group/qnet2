import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  createAgentFiscalProfile,
  deleteAgentFiscalProfile,
  fetchAgentFiscalProfile,
  updateAgentFiscalProfile,
} from '@/features/agent-fiscal-profiles/api'
import type { AgentFiscalProfilePayload } from '@/features/agent-fiscal-profiles/types'

const client = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }))
vi.mock('@/api/client', () => ({ apiClient: client }))

const PAYLOAD: AgentFiscalProfilePayload = {
  referent_id: 5,
  withholding_enabled: false,
  withholding_rate: null,
  withholding_base_percent: null,
  enasarco_enabled: false,
  enasarco_mandate_type: null,
  vat_rate_id: null,
  valid_from: '2026-01-01',
  valid_until: null,
  notes: null,
}

const PERMISSIONS = { resource: {}, fields: {}, actions: {} }

describe('agent fiscal profiles api (spec 0212 data_contract, AC-020)', () => {
  beforeEach(() => {
    Object.values(client).forEach((mock) => mock.mockReset())
  })

  it('reads one profile with the envelope permissions merged in', async () => {
    client.get.mockResolvedValue({ data: { data: { id: 3 }, permissions: PERMISSIONS } })

    const profile = await fetchAgentFiscalProfile(3)

    expect(client.get).toHaveBeenCalledWith('/agent-fiscal-profiles/3')
    expect(profile).toEqual({ id: 3, permissions: PERMISSIONS })
  })

  it('creates with POST /agent-fiscal-profiles', async () => {
    client.post.mockResolvedValue({ data: { data: { id: 8 } } })

    await expect(createAgentFiscalProfile(PAYLOAD)).resolves.toEqual({ id: 8 })
    expect(client.post).toHaveBeenCalledWith('/agent-fiscal-profiles', PAYLOAD)
  })

  // REQUIREMENT CHANGED (spec 0264 D-1): the in-place rows send a partial PATCH and get the permissions back.
  it('updates with a partial PATCH /agent-fiscal-profiles/{id}, permissions included', async () => {
    const permissions = { resource: {}, fields: {}, actions: {} }
    client.patch.mockResolvedValue({ data: { data: { id: 8 }, permissions } })

    const saved = await updateAgentFiscalProfile(8, { notes: 'ok' })

    expect(client.patch).toHaveBeenCalledWith('/agent-fiscal-profiles/8', { notes: 'ok' })
    expect(saved).toEqual({ id: 8, permissions })
  })

  it('deletes with DELETE /agent-fiscal-profiles/{id}', async () => {
    client.delete.mockResolvedValue({ status: 204 })

    await deleteAgentFiscalProfile(8)

    expect(client.delete).toHaveBeenCalledWith('/agent-fiscal-profiles/8')
  })
})
