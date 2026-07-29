import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useStallTimeout } from '@/hooks/use-stall-timeout'

/**
 * The guard that stops a poll whose queued job is never consumed: it flips to
 * stalled only after the full window, restarts that window on every phase
 * change (a phase that keeps progressing is never stalled), and stays quiet
 * while nothing is polled.
 */

const TIMEOUT_MS = 1000

beforeEach(() => {
  vi.useFakeTimers()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useStallTimeout', () => {
  it('stays quiet until the window elapses, then reports the stall', () => {
    const { result } = renderHook(() => useStallTimeout('run-1:analyzing', TIMEOUT_MS))

    expect(result.current.isStalled).toBe(false)

    act(() => vi.advanceTimersByTime(TIMEOUT_MS - 1))
    expect(result.current.isStalled).toBe(false)

    act(() => vi.advanceTimersByTime(1))
    expect(result.current.isStalled).toBe(true)
  })

  it('never stalls a phase that keeps advancing', () => {
    const { result, rerender } = renderHook(({ phase }) => useStallTimeout(phase, TIMEOUT_MS), {
      initialProps: { phase: 'run-1:analyzing' },
    })

    act(() => vi.advanceTimersByTime(TIMEOUT_MS - 1))
    rerender({ phase: 'run-1:staging' })

    // The new phase gets a full window, not the remainder of the previous one.
    act(() => vi.advanceTimersByTime(TIMEOUT_MS - 1))
    expect(result.current.isStalled).toBe(false)

    act(() => vi.advanceTimersByTime(1))
    expect(result.current.isStalled).toBe(true)
  })

  it('clears the stall and restarts the window on reset', () => {
    const { result } = renderHook(() => useStallTimeout('run-1:processing', TIMEOUT_MS))

    act(() => vi.advanceTimersByTime(TIMEOUT_MS))
    expect(result.current.isStalled).toBe(true)

    act(() => result.current.resetStall())
    expect(result.current.isStalled).toBe(false)

    act(() => vi.advanceTimersByTime(TIMEOUT_MS))
    expect(result.current.isStalled).toBe(true)
  })

  it('reports no stall while nothing is being polled', () => {
    const { result } = renderHook(() => useStallTimeout(null, TIMEOUT_MS))

    act(() => vi.advanceTimersByTime(TIMEOUT_MS * 10))
    expect(result.current.isStalled).toBe(false)
  })
})
