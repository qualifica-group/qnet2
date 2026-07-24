import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useUpdateRewardStatus } from '@/features/rewards/use-update-reward-status'
import type { RewardDetailItem } from '@/features/rewards/types'

const patchRewardStatusMock = vi.fn<(rewardId: number, rewardStatusId: number) => Promise<RewardDetailItem>>()

vi.mock('@/features/rewards/api', () => ({
  patchRewardStatus: (rewardId: number, rewardStatusId: number) =>
    patchRewardStatusMock(rewardId, rewardStatusId),
}))

beforeEach(() => {
  patchRewardStatusMock.mockReset()
})

function wrapper(client: QueryClient) {
  return function QueryWrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
}

const UPDATED_REWARD: RewardDetailItem = {
  id: 10,
  assigned_at: '2026-07-01',
  notes: null,
  reward_type: { id: 1, name: 'Amazon voucher', color: 'blue' },
  source: null,
  context: null,
  reward_status: { id: 5, name: 'Approved', color: 'green' },
}

/** Spec 0060 §4/AC-029: the mutation behind the card's inline status edit. */
describe('useUpdateRewardStatus', () => {
  it('calls patchRewardStatus with the picked variables and fires onSuccess with the response', async () => {
    patchRewardStatusMock.mockResolvedValue(UPDATED_REWARD)
    const onSuccess = vi.fn()
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    const { result } = renderHook(() => useUpdateRewardStatus({ onSuccess }), {
      wrapper: wrapper(client),
    })

    result.current.mutate({ rewardId: 10, rewardStatusId: 5 })

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(patchRewardStatusMock).toHaveBeenCalledWith(10, 5)
    expect(onSuccess).toHaveBeenCalledTimes(1)
    // TanStack Query's onSuccess forwards (data, variables, context, mutation);
    // only the first two — this hook's own contract — matter here.
    expect(onSuccess.mock.calls[0]?.[0]).toEqual(UPDATED_REWARD)
    expect(onSuccess.mock.calls[0]?.[1]).toEqual({ rewardId: 10, rewardStatusId: 5 })
  })

  it('exposes the failure instead of throwing so the caller can render an inline error', async () => {
    patchRewardStatusMock.mockRejectedValue(new Error('network down'))
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    const { result } = renderHook(() => useUpdateRewardStatus(), { wrapper: wrapper(client) })

    result.current.mutate({ rewardId: 10, rewardStatusId: 5 })

    await waitFor(() => expect(result.current.isError).toBe(true))
  })
})
