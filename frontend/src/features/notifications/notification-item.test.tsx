import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import i18n from '@/i18n'
import { NotificationItem } from '@/features/notifications/notification-item'
import type { Notification } from '@/features/notifications/types'

const navigateMock = vi.fn()

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>()
  return { ...actual, useNavigate: () => navigateMock }
})

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  navigateMock.mockReset()
})

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

describe('NotificationItem', () => {
  it('renders the title and message from the data payload', () => {
    render(
      <NotificationItem
        notification={buildNotification()}
        onMarkAsRead={vi.fn()}
      />,
    )
    expect(screen.getByText('Hello')).toBeInTheDocument()
    expect(screen.getByText('World')).toBeInTheDocument()
  })

  it('shows the mark-as-read action only while unread', () => {
    const { rerender } = render(
      <NotificationItem
        notification={buildNotification({ read_at: null })}
        onMarkAsRead={vi.fn()}
      />,
    )
    expect(
      screen.getByRole('button', { name: 'Mark as read' }),
    ).toBeInTheDocument()

    rerender(
      <NotificationItem
        notification={buildNotification({ read_at: '2026-06-15T11:00:00.000Z' })}
        onMarkAsRead={vi.fn()}
      />,
    )
    expect(
      screen.queryByRole('button', { name: 'Mark as read' }),
    ).not.toBeInTheDocument()
  })
})

describe('NotificationItem — action_url navigation (spec 0078, AC-027)', () => {
  it('navigates and marks as read when action_url is a safe internal path', () => {
    const onMarkAsRead = vi.fn()
    render(
      <NotificationItem
        notification={buildNotification({
          data: { title: 'New request', message: null, level: 'info', action_url: '/field-change-requests/12' },
        })}
        onMarkAsRead={onMarkAsRead}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: /New request/ }))

    expect(navigateMock).toHaveBeenCalledWith('/field-change-requests/12')
    expect(onMarkAsRead).toHaveBeenCalledWith('n1')
  })

  it('dismisses the host panel after navigating, so the modal menu does not cover the target page', () => {
    const onNavigate = vi.fn()
    render(
      <NotificationItem
        notification={buildNotification({
          data: { title: 'New request', message: null, level: 'info', action_url: '/registries/8' },
        })}
        onMarkAsRead={vi.fn()}
        onNavigate={onNavigate}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: /New request/ }))

    expect(navigateMock).toHaveBeenCalledWith('/registries/8')
    expect(onNavigate).toHaveBeenCalledTimes(1)
  })

  it('tints the whole row on hover with the AA-safe muted veil, not accent', () => {
    const { container } = render(
      <NotificationItem
        notification={buildNotification({
          data: { title: 'New request', message: 'Body', level: 'info', action_url: '/registries/8' },
        })}
        onMarkAsRead={vi.fn()}
      />,
    )

    // The row, not the inner button: the whole strip must react as one item.
    const row = container.firstElementChild as HTMLElement
    expect(row).toHaveClass('hover:bg-muted')
    // `text-muted-foreground` on `--accent` measures 4.17:1 light / 2.71:1
    // dark — below AA. Guards against a swap back to the accent veil.
    expect(row).not.toHaveClass('hover:bg-accent')
  })

  it('shows a pointer cursor on the clickable row (Tailwind 4 gives buttons none)', () => {
    render(
      <NotificationItem
        notification={buildNotification({
          data: { title: 'New request', message: null, level: 'info', action_url: '/registries/8' },
        })}
        onMarkAsRead={vi.fn()}
      />,
    )

    expect(screen.getByRole('button', { name: /New request/ })).toHaveClass('cursor-pointer')
  })

  it('navigates but does not re-mark an already read notification', () => {
    const onMarkAsRead = vi.fn()
    render(
      <NotificationItem
        notification={buildNotification({
          read_at: '2026-06-15T11:00:00.000Z',
          data: { title: 'New request', message: null, level: 'info', action_url: '/field-change-requests/12' },
        })}
        onMarkAsRead={onMarkAsRead}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: /New request/ }))

    expect(navigateMock).toHaveBeenCalledWith('/field-change-requests/12')
    expect(onMarkAsRead).not.toHaveBeenCalled()
  })

  it('renders no clickable element and never navigates when action_url is absent', () => {
    const onMarkAsRead = vi.fn()
    render(
      <NotificationItem
        notification={buildNotification({
          data: { title: 'No link', message: null, level: 'info', action_url: null },
        })}
        onMarkAsRead={onMarkAsRead}
      />,
    )

    expect(screen.queryByRole('button', { name: /No link/ })).not.toBeInTheDocument()
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it.each([
    ['an absolute URL', 'https://evil.com/x'],
    ['a protocol-relative URL', '//evil.com/x'],
    ['a javascript: URL', 'javascript:alert(1)'],
  ])('renders no clickable element and never navigates for %s', (_label, actionUrl) => {
    const onMarkAsRead = vi.fn()
    render(
      <NotificationItem
        notification={buildNotification({
          data: { title: 'Unsafe link', message: null, level: 'info', action_url: actionUrl },
        })}
        onMarkAsRead={onMarkAsRead}
      />,
    )

    expect(screen.queryByRole('button', { name: /Unsafe link/ })).not.toBeInTheDocument()
    expect(navigateMock).not.toHaveBeenCalled()
  })
})
