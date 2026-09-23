import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook } from '@testing-library/react'
import i18n from '@/i18n'
import { formatUnreadBadgeCount, useUnreadBadge } from '@/features/notifications/use-unread-badge'

const useUnreadSummaryMock = vi.fn()
vi.mock('@/features/notifications/use-notifications', () => ({
  useUnreadSummary: () => useUnreadSummaryMock(),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useUnreadSummaryMock.mockReset()
})

describe('formatUnreadBadgeCount', () => {
  it('renders the exact count at or below the cap', () => {
    expect(formatUnreadBadgeCount(7)).toBe('7')
    expect(formatUnreadBadgeCount(99)).toBe('99')
  })

  it('caps to "99+" beyond the cap', () => {
    expect(formatUnreadBadgeCount(100)).toBe('99+')
    expect(formatUnreadBadgeCount(250)).toBe('99+')
  })
})

describe('useUnreadBadge', () => {
  it('has no label when the count is 0', () => {
    useUnreadSummaryMock.mockReturnValue({ data: { count: 0, latest: null } })

    const { result } = renderHook(() => useUnreadBadge())

    expect(result.current.count).toBe(0)
    expect(result.current.label).toBeNull()
  })

  it('renders the exact count and an aria-label carrying it', () => {
    useUnreadSummaryMock.mockReturnValue({ data: { count: 7, latest: null } })

    const { result } = renderHook(() => useUnreadBadge())

    expect(result.current.label).toBe('7')
    expect(result.current.ariaLabel).toContain('7')
  })

  it('caps the label to "99+" while the aria-label keeps the exact count', () => {
    useUnreadSummaryMock.mockReturnValue({ data: { count: 142, latest: null } })

    const { result } = renderHook(() => useUnreadBadge())

    expect(result.current.label).toBe('99+')
    expect(result.current.ariaLabel).toContain('142')
    expect(result.current.ariaLabel).not.toContain('99+')
  })
})
