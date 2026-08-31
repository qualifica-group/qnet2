import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  changeContractStatus,
  reactivateContract,
  terminateContract,
  updateContract,
  validateContract,
} from '@/features/contracts/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
}))

const postMock = vi.mocked(apiClient.post)
const patchMock = vi.mocked(apiClient.patch)

/** The action flags the detail's bar reads; they follow the contract's status group. */
const PERMISSIONS = {
  resource: { view: true, create: false, update: false, delete: false, export: true, import: false },
  fields: {},
  actions: { validate: false, schedule: true, terminate: true, reactivate: false, change_status: false },
}

function envelope() {
  return {
    data: {
      success: true,
      message: 'ok',
      data: { id: 1, contract_status_id: 2 },
      permissions: PERMISSIONS,
    },
  }
}

beforeEach(() => {
  postMock.mockReset()
  patchMock.mockReset()
  postMock.mockResolvedValue(envelope())
  patchMock.mockResolvedValue(envelope())
})

/**
 * Regression (segnalazione utente 2026-08-31): the bar kept rendering the
 * previous status group's buttons until the page was reloaded, because these
 * writes returned `data` alone and dropped the `permissions` envelope the
 * backend sends with every one of them (`okWithPermissions`). The action
 * flags depend on the contract's own status group, so they MUST travel back
 * with each write.
 */
describe('contracts api — every write returns the refreshed permissions', () => {
  it('returns them from the four action endpoints', async () => {
    const results = await Promise.all([
      validateContract(1, {}),
      changeContractStatus(1, { contract_status_id: 2 }),
      terminateContract(1, { terminated_at: '2026-08-31', termination_reason: 'x' }),
      reactivateContract(1, { contract_status_id: 2 }),
    ])

    for (const result of results) {
      expect(result.permissions).toEqual(PERMISSIONS)
      expect(result.contract_status_id).toBe(2)
    }
  })

  it('returns them from the PATCH as well', async () => {
    const result = await updateContract(1, { comments: 'nota' })

    expect(result.permissions).toEqual(PERMISSIONS)
  })
})
