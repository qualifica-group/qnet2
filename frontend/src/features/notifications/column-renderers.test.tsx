import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { BadgeCell } from '@/features/table/cell-renderers'
import { notificationColumnRenderers } from '@/features/notifications/column-renderers'
import type { EnumBadge } from '@/features/table/types'

const navigateMock = vi.fn()
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>()
  return { ...actual, useNavigate: () => navigateMock }
})

const markAsReadMutateMock = vi.fn()
vi.mock('@/features/notifications/use-notification-actions', () => ({
  useNotificationActions: () => ({
    markAsRead: { mutate: markAsReadMutateMock },
  }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  navigateMock.mockReset()
  markAsReadMutateMock.mockReset()
})

function renderActionUrl(data: Record<string, unknown> | undefined) {
  const params = { data } as unknown as ICellRendererParams
  const Renderer = notificationColumnRenderers.action_url
  return render(<>{Renderer?.(params)}</>)
}

/**
 * `action_url` cell (spec 0150 AC-012): the sole custom renderer this domain
 * needs — `status`/`level` fall through to the generic `BadgeCell` (verified
 * below, not re-implemented here).
 */
describe('notifications action_url column — AC-012', () => {
  it('shows "Open" and marks the row read, then navigates, for an unread row with a safe path', () => {
    renderActionUrl({ id: 'n1', status: 'unread', action_url: '/field-change-requests/12' })

    fireEvent.click(screen.getByRole('button', { name: 'Open' }))

    expect(markAsReadMutateMock).toHaveBeenCalledWith('n1')
    expect(navigateMock).toHaveBeenCalledWith('/field-change-requests/12')
  })

  it('only navigates, without marking as read, for an already-read row', () => {
    renderActionUrl({ id: 'n2', status: 'read', action_url: '/registries/8' })

    fireEvent.click(screen.getByRole('button', { name: 'Open' }))

    expect(markAsReadMutateMock).not.toHaveBeenCalled()
    expect(navigateMock).toHaveBeenCalledWith('/registries/8')
  })

  it('renders nothing when action_url is null', () => {
    renderActionUrl({ id: 'n3', status: 'unread', action_url: null })

    expect(screen.queryByRole('button', { name: 'Open' })).not.toBeInTheDocument()
  })

  it.each([
    ['an absolute URL', 'https://evil.com/x'],
    ['a protocol-relative URL', '//evil.com/x'],
    ['a javascript: URL', 'javascript:alert(1)'],
  ])('renders nothing when action_url is unsafe (%s)', (_label, actionUrl) => {
    renderActionUrl({ id: 'n4', status: 'unread', action_url: actionUrl })

    expect(screen.queryByRole('button', { name: 'Open' })).not.toBeInTheDocument()
  })
})

/**
 * AC-009: the `status`/`level` badges carry a TEXTUAL label (not color alone).
 * Both columns declare an `enumKey` server-side (`notification_status`/
 * `notification_level`), so the generic `BadgeCell` localizes from the FE
 * i18n resources added for this domain — this pins those specific entries,
 * `BadgeCell`'s own enumKey behavior already being covered generically.
 */
describe('notifications status/level badges — AC-009', () => {
  afterEach(async () => {
    await i18n.changeLanguage('en')
  })

  it('renders the Italian "Non letta"/"Letta" labels for the status badge', async () => {
    await i18n.changeLanguage('it')
    const badges: EnumBadge[] = [
      { value: 'unread', label: 'Unread', color: 'blue', icon: null },
      { value: 'read', label: 'Read', color: 'slate', icon: null },
    ]
    const params = { value: 'unread', badges, enumKey: 'notification_status' } as unknown as ICellRendererParams
    render(<BadgeCell {...params} />)

    expect(screen.getByText('Non letta')).toBeInTheDocument()
  })

  it('renders a textual label for the level badge, not just a color', () => {
    const badges: EnumBadge[] = [
      { value: 'warning', label: 'Warning', color: 'amber', icon: 'alert-triangle' },
    ]
    const params = { value: 'warning', badges, enumKey: 'notification_level' } as unknown as ICellRendererParams
    render(<BadgeCell {...params} />)

    expect(screen.getByText('Warning')).toBeInTheDocument()
  })
})
