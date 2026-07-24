import { beforeEach, describe, expect, it, vi } from 'vitest'
import { patchRewardStatus } from '@/features/rewards/api'
import { apiClient } from '@/api/client'
import type { RewardDetailItem } from '@/features/rewards/types'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
}))

const patchMock = vi.mocked(apiClient.patch)

beforeEach(() => {
  patchMock.mockReset()
})

const RESPONSE_REWARD: RewardDetailItem = {
  id: 10,
  assigned_at: '2026-07-01',
  notes: null,
  reward_type: { id: 1, name: 'Amazon voucher', color: 'blue' },
  source: null,
  context: null,
  reward_status: { id: 5, name: 'Approved', color: 'green' },
}

/** Spec 0060 §4: `PATCH /api/rewards/{reward}` accepts only `{ reward_status_id }`. */
describe('patchRewardStatus', () => {
  it('sends the reward status id as the sole PATCH body field (AC-029)', async () => {
    patchMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: RESPONSE_REWARD },
    })

    const result = await patchRewardStatus(10, 5)

    expect(patchMock).toHaveBeenCalledWith('/rewards/10', { reward_status_id: 5 })
    expect(patchMock).toHaveBeenCalledTimes(1)
    expect(result).toEqual(RESPONSE_REWARD)
  })
})
