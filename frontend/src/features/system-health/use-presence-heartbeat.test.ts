// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook } from '@testing-library/react'
import {
  PRESENCE_HEARTBEAT_INTERVAL_MS,
  usePresenceHeartbeat,
} from '@/features/system-health/use-presence-heartbeat'

const sendMock = vi.fn()

vi.mock('@/features/system-health/api', () => ({
  sendPresenceHeartbeat: () => sendMock(),
}))

describe('usePresenceHeartbeat (AC-015)', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    sendMock.mockReset().mockResolvedValue(undefined)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('pings at mount, then every 60000 ms, and stops on unmount', () => {
    expect(PRESENCE_HEARTBEAT_INTERVAL_MS).toBe(60000)

    const { unmount } = renderHook(() => usePresenceHeartbeat())
    expect(sendMock).toHaveBeenCalledTimes(1)

    vi.advanceTimersByTime(59999)
    expect(sendMock).toHaveBeenCalledTimes(1)
    vi.advanceTimersByTime(1)
    expect(sendMock).toHaveBeenCalledTimes(2)
    vi.advanceTimersByTime(60000)
    expect(sendMock).toHaveBeenCalledTimes(3)

    unmount()
    vi.advanceTimersByTime(180000)
    expect(sendMock).toHaveBeenCalledTimes(3)
  })

  it('swallows a failing ping without throwing', async () => {
    sendMock.mockRejectedValue(new Error('network'))

    expect(() => renderHook(() => usePresenceHeartbeat())).not.toThrow()
    await vi.advanceTimersByTimeAsync(60000)

    expect(sendMock).toHaveBeenCalledTimes(2)
  })
})
