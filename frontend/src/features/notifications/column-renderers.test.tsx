import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { BadgeCell } from '@/features/table/cell-renderers'
import { notificationColumnRenderers } from '@/features/notifications/column-renderers'
import type { EnumBadge } from '@/features/table/types'

const markAsReadMutateMock = vi.fn()
vi.mock('@/features/notifications/use-notification-actions', () => ({
  useNotificationActions: () => ({
    markAsRead: { mutate: markAsReadMutateMock },
  }),
}))

// A stand-in registry: `/registries/:id` is a module record, anything else is not.
vi.mock('@/features/modules/module-registry', () => ({
  findModuleRecordByPath: (path: string) => {
    const match = /^\/registries\/(\d+)$/.exec(path)
    return match ? { domain: 'registries', id: Number(match[1]) } : null
  },
}))

const modalOnClickMock = vi.fn((event: { preventDefault: () => void }) => event.preventDefault())
const useRecordModalLinkMock = vi.fn<
  (domain: string, id: number) => { onClick: typeof modalOnClickMock; sheet: null }
>(() => ({ onClick: modalOnClickMock, sheet: null }))
vi.mock('@/features/modules/use-record-modal-link', () => ({
  useRecordModalLink: (domain: string, id: number) => useRecordModalLinkMock(domain, id),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  markAsReadMutateMock.mockReset()
  modalOnClickMock.mockClear()
  useRecordModalLinkMock.mockClear()
})

function renderActionUrl(data: Record<string, unknown> | undefined) {
  const params = { data } as unknown as ICellRendererParams
  const Renderer = notificationColumnRenderers.action_url
  return render(
    <MemoryRouter initialEntries={['/notifications']}>
      <Routes>
        <Route path="/notifications" element={<>{Renderer?.(params)}</>} />
        <Route path="*" element={<p>navigated</p>} />
      </Routes>
    </MemoryRouter>,
  )
}

/**
 * `action_url` cell (spec 0150 AC-012, revised 2026-09-23): the sole custom
 * renderer this domain needs — `status`/`level` fall through to the generic
 * `BadgeCell` (verified below, not re-implemented here).
 */
describe('notifications action_url column — AC-012', () => {
  it('opens a module record in the modal, without navigating, and marks an unread row read', () => {
    renderActionUrl({ id: 'n1', status: 'unread', action_url: '/registries/12' })

    const link = screen.getByRole('link', { name: 'Open' })
    expect(link).toHaveAttribute('href', '/registries/12')
    fireEvent.click(link)

    expect(useRecordModalLinkMock).toHaveBeenCalledWith('registries', 12)
    expect(modalOnClickMock).toHaveBeenCalledTimes(1)
    expect(markAsReadMutateMock).toHaveBeenCalledWith('n1', expect.any(Object))
    expect(screen.queryByText('navigated')).not.toBeInTheDocument()
  })

  it('opens the modal without marking as read for an already-read row', () => {
    renderActionUrl({ id: 'n2', status: 'read', action_url: '/registries/8' })

    fireEvent.click(screen.getByRole('link', { name: 'Open' }))

    expect(modalOnClickMock).toHaveBeenCalledTimes(1)
    expect(markAsReadMutateMock).not.toHaveBeenCalled()
  })

  it('follows a non-module path as a plain link, marking an unread row read', () => {
    renderActionUrl({ id: 'n5', status: 'unread', action_url: '/imports/3' })

    fireEvent.click(screen.getByRole('link', { name: 'Open' }))

    expect(useRecordModalLinkMock).not.toHaveBeenCalled()
    expect(markAsReadMutateMock).toHaveBeenCalledWith('n5', expect.any(Object))
    expect(screen.getByText('navigated')).toBeInTheDocument()
  })

  it('renders nothing when action_url is null', () => {
    renderActionUrl({ id: 'n3', status: 'unread', action_url: null })

    expect(screen.queryByRole('link', { name: 'Open' })).not.toBeInTheDocument()
  })

  it.each([
    ['an absolute URL', 'https://evil.com/x'],
    ['a protocol-relative URL', '//evil.com/x'],
    ['a javascript: URL', 'javascript:alert(1)'],
  ])('renders nothing when action_url is unsafe (%s)', (_label, actionUrl) => {
    renderActionUrl({ id: 'n4', status: 'unread', action_url: actionUrl })

    expect(screen.queryByRole('link', { name: 'Open' })).not.toBeInTheDocument()
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
