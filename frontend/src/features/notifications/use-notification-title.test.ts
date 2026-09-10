import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { env } from '@/config/env'
import { useNotificationTitle } from '@/features/notifications/use-notification-title'
import type { Notification, UnreadSummary } from '@/features/notifications/types'

const useUnreadSummaryMock = vi.fn()

vi.mock('@/features/notifications/use-notifications', () => ({
  useUnreadSummary: () => useUnreadSummaryMock(),
}))

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}))

function buildNotification(title: string | null): Notification {
  return {
    id: 'n1',
    type: 'generic',
    data: { title, message: 'Body', level: 'info', action_url: null },
    read_at: null,
    created_at: '2026-09-10T10:00:00+00:00',
  }
}

function mockSummary(summary: UnreadSummary | undefined) {
  useUnreadSummaryMock.mockReturnValue({ data: summary })
}

beforeEach(() => {
  vi.useFakeTimers()
  useUnreadSummaryMock.mockReset()
  document.title = 'stale'
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useNotificationTitle', () => {
  it('keeps the plain app name while nothing is unread', () => {
    mockSummary({ count: 0, latest: null })

    renderHook(() => useNotificationTitle())

    expect(document.title).toBe(env.appName)
  })

  it('alternates between the app name and the latest notification, both counted', () => {
    mockSummary({ count: 3, latest: buildNotification('New request assigned') })

    renderHook(() => useNotificationTitle())

    expect(document.title).toBe(`(3) ${env.appName}`)

    act(() => {
      vi.advanceTimersByTime(2000)
    })
    expect(document.title).toBe('(3) \u{1F514} New request assigned')

    act(() => {
      vi.advanceTimersByTime(2000)
    })
    expect(document.title).toBe(`(3) ${env.appName}`)
  })

  it('falls back to the untitled label when the notification carries no title', () => {
    mockSummary({ count: 1, latest: buildNotification(null) })

    renderHook(() => useNotificationTitle())

    act(() => {
      vi.advanceTimersByTime(2000)
    })
    expect(document.title).toBe('(1) \u{1F514} notifications.untitled')
  })

  it('restores the app name on unmount', () => {
    mockSummary({ count: 1, latest: buildNotification('Something') })

    const { unmount } = renderHook(() => useNotificationTitle())
    unmount()

    expect(document.title).toBe(env.appName)
  })

  it('stops alternating once the summary reports everything read', () => {
    mockSummary({ count: 1, latest: buildNotification('Something') })
    const { rerender } = renderHook(() => useNotificationTitle())

    mockSummary({ count: 0, latest: null })
    rerender()

    act(() => {
      vi.advanceTimersByTime(6000)
    })
    expect(document.title).toBe(env.appName)
  })
})
