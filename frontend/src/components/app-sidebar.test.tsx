import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { AppSidebar } from '@/components/app-sidebar'
import { SidebarProvider } from '@/components/ui/sidebar'

// `SidebarProvider` (real) reads `window.matchMedia` via `hooks/use-mobile.ts`
// to track the mobile breakpoint; jsdom does not implement it (same stub as
// `layouts/app-layout.test.tsx`).
if (typeof window.matchMedia !== 'function') {
  window.matchMedia = ((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  })) as unknown as typeof window.matchMedia
}

/**
 * The sidebar footer's fixed "Notifiche" link (spec 0150 D-4 revised,
 * AC-016): NOT part of the backend-driven navigation tree (mocked here as
 * empty — `<NavMain>`'s own rendering is out of this suite's scope), a
 * hardcoded entry right above "Impostazioni" carrying the unread badge off
 * `useUnreadBadge` (unit-tested on its own in
 * `features/notifications/use-unread-badge.test.ts`; mocked here so this
 * suite only asserts what `AppSidebar` does with it).
 */

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ logout: vi.fn(), isAuthenticated: true }),
}))

vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: [], isPending: false, isError: false }),
}))

const useUnreadBadgeMock = vi.fn()
vi.mock('@/features/notifications/use-unread-badge', () => ({
  useUnreadBadge: () => useUnreadBadgeMock(),
}))

function renderSidebar(initialPath = '/dashboard') {
  return render(
    <MemoryRouter initialEntries={[initialPath]}>
      <SidebarProvider>
        <AppSidebar />
      </SidebarProvider>
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  useUnreadBadgeMock.mockReset()
  useUnreadBadgeMock.mockReturnValue({ count: 0, label: null, ariaLabel: '0 unread notifications' })
})

describe('AppSidebar — footer "Notifiche" link (spec 0150, AC-016)', () => {
  it('renders "Notifiche" immediately before "Impostazioni" in the footer', () => {
    renderSidebar()

    const links = screen.getAllByRole('link', { name: /Notifiche|Impostazioni/ })
    expect(links.map((link) => link.textContent)).toEqual(['Notifiche', 'Impostazioni'])
  })

  it('shows no badge when there are no unread notifications', () => {
    renderSidebar()

    expect(screen.queryByLabelText(/notifiche/i)).not.toBeInTheDocument()
  })

  it('shows the exact count as an accessible badge', () => {
    useUnreadBadgeMock.mockReturnValue({ count: 7, label: '7', ariaLabel: '7 notifiche non lette' })

    renderSidebar()

    const badge = screen.getByLabelText('7 notifiche non lette')
    expect(badge).toHaveTextContent('7')
  })

  it('caps the visible badge to "99+" while the accessible label keeps the exact count', () => {
    useUnreadBadgeMock.mockReturnValue({
      count: 142,
      label: '99+',
      ariaLabel: '142 notifiche non lette',
    })

    renderSidebar()

    const badge = screen.getByLabelText('142 notifiche non lette')
    expect(badge).toHaveTextContent('99+')
  })

  it('marks the link active on /notifications', () => {
    renderSidebar('/notifications')

    expect(screen.getByRole('link', { name: 'Notifiche' })).toHaveAttribute(
      'aria-current',
      'page',
    )
    expect(screen.getByRole('link', { name: 'Impostazioni' })).not.toHaveAttribute(
      'aria-current',
    )
  })
})
