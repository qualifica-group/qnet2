import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { NotificationBell } from '@/features/notifications/notification-bell'
import type { Notification, NotificationFilter } from '@/features/notifications/types'

/**
 * `NotificationItem` (spec 0078, AC-027) reads `useNavigate()` to make
 * `action_url` clickable, so every render needs a Router ancestor now.
 */
function renderBell() {
  return render(
    <MemoryRouter>
      <NotificationBell />
    </MemoryRouter>,
  )
}

const useUnreadSummaryMock = vi.fn()
const useNotificationListMock = vi.fn()
const markAsReadMutateMock = vi.fn()
const markAllAsReadMutateMock = vi.fn()

vi.mock('@/features/notifications/use-notifications', () => ({
  useUnreadSummary: () => useUnreadSummaryMock(),
  useNotificationList: (open: boolean, filter: NotificationFilter) =>
    useNotificationListMock(open, filter),
}))

vi.mock('@/features/notifications/use-notification-actions', () => ({
  useNotificationActions: () => ({
    markAsRead: {
      mutate: markAsReadMutateMock,
      isPending: false,
      variables: undefined,
    },
    markAllAsRead: {
      mutate: markAllAsReadMutateMock,
      isPending: false,
    },
  }),
}))

/**
 * Radix' DropdownMenu trigger opens on `pointerdown`, not `click`, so a plain
 * `fireEvent.click` leaves the panel closed in jsdom. Drive the real pointer
 * sequence instead.
 */
function openPanel() {
  fireEvent.pointerDown(
    screen.getByRole('button', { name: 'Open notifications' }),
    { button: 0, ctrlKey: false },
  )
}

function buildNotification(overrides: Partial<Notification> = {}): Notification {
  return {
    id: 'n1',
    type: 'generic',
    data: { title: 'Hello', message: 'World', level: 'info', action_url: null },
    read_at: null,
    created_at: '2026-06-15T10:00:00.000Z',
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useUnreadSummaryMock.mockReset()
  useNotificationListMock.mockReset()
  markAsReadMutateMock.mockReset()
  markAllAsReadMutateMock.mockReset()

  useUnreadSummaryMock.mockReturnValue({ data: { count: 2, latest: null } })
  useNotificationListMock.mockReturnValue({
    data: {
      pages: [
        {
          items: [
            buildNotification({ id: 'unread-1' }),
            buildNotification({
              id: 'read-1',
              data: {
                title: 'Already read',
                message: 'Done',
                level: 'info',
                action_url: null,
              },
              read_at: '2026-06-15T11:00:00.000Z',
            }),
          ],
        },
      ],
    },
    isPending: false,
    isError: false,
    refetch: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    fetchNextPage: vi.fn(),
  })
})

describe('NotificationBell', () => {
  it('shows all, unread and read filters inside the panel', () => {
    renderBell()

    openPanel()

    expect(screen.getByRole('button', { name: 'All' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Unread' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Read' })).toBeInTheDocument()
  })

  it('requests the unread server filter when the user selects Unread', async () => {
    renderBell()

    openPanel()
    fireEvent.click(screen.getByRole('button', { name: 'Unread' }))

    await waitFor(() =>
      expect(useNotificationListMock).toHaveBeenLastCalledWith(true, 'unread'),
    )
  })

  it('keeps using the all feed for Read and filters visible rows locally', async () => {
    renderBell()

    openPanel()
    fireEvent.click(screen.getByRole('button', { name: 'Read' }))

    await waitFor(() =>
      expect(useNotificationListMock).toHaveBeenLastCalledWith(true, 'all'),
    )
    expect(screen.getByText('Already read')).toBeInTheDocument()
    expect(screen.queryByText('Hello')).not.toBeInTheDocument()
  })

  it('prefetches the next page while the Read filter has no visible matches yet', async () => {
    const fetchNextPage = vi.fn()
    useNotificationListMock.mockReturnValue({
      data: {
        pages: [
          {
            items: [buildNotification({ id: 'unread-only-1' })],
          },
        ],
      },
      isPending: false,
      isError: false,
      refetch: vi.fn(),
      hasNextPage: true,
      isFetchingNextPage: false,
      fetchNextPage,
    })

    renderBell()

    openPanel()
    fireEvent.click(screen.getByRole('button', { name: 'Read' }))

    await waitFor(() => expect(fetchNextPage).toHaveBeenCalledTimes(1))
  })
})
