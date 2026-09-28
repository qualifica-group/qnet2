// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { statsPanelId, useStatsPanel } from '@/features/stats/use-stats-panel'

/**
 * Spec 0026 AC-008 — the open/closed preference is persisted per module under
 * `stats-panel:{domain}` and survives an unavailable storage.
 */

const DOMAIN = 'leads'
const STORAGE_KEY = 'stats-panel:leads'

beforeEach(() => {
  window.localStorage.clear()
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('useStatsPanel', () => {
  it('is closed by default when nothing was stored', () => {
    const { result } = renderHook(() => useStatsPanel(DOMAIN))

    expect(result.current.isOpen).toBe(false)
  })

  it('persists the open state under the per-domain key', () => {
    const { result } = renderHook(() => useStatsPanel(DOMAIN))

    act(() => result.current.toggle())

    expect(result.current.isOpen).toBe(true)
    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('true')
  })

  it('restores the stored preference on the next mount', () => {
    window.localStorage.setItem(STORAGE_KEY, 'true')

    const { result } = renderHook(() => useStatsPanel(DOMAIN))

    expect(result.current.isOpen).toBe(true)
  })

  it('persists the closed state again when toggled back', () => {
    window.localStorage.setItem(STORAGE_KEY, 'true')
    const { result } = renderHook(() => useStatsPanel(DOMAIN))

    act(() => result.current.toggle())

    expect(result.current.isOpen).toBe(false)
    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('false')
  })

  it('keeps two modules independent', () => {
    window.localStorage.setItem(STORAGE_KEY, 'true')

    const { result } = renderHook(() => useStatsPanel('users'))

    expect(result.current.isOpen).toBe(false)
  })

  it('still toggles for the session when the storage throws', () => {
    vi.spyOn(window.localStorage, 'getItem').mockImplementation(() => {
      throw new Error('storage unavailable')
    })
    vi.spyOn(window.localStorage, 'setItem').mockImplementation(() => {
      throw new Error('storage unavailable')
    })

    const { result } = renderHook(() => useStatsPanel(DOMAIN))
    expect(result.current.isOpen).toBe(false)

    act(() => result.current.toggle())

    expect(result.current.isOpen).toBe(true)
  })
})

describe('statsPanelId', () => {
  it('derives the id the toggle button points at with aria-controls', () => {
    expect(statsPanelId(DOMAIN)).toBe('stats-panel-leads')
  })
})
