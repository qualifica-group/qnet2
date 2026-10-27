import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { AppSidebar } from '@/components/app-sidebar'
import { SidebarProvider } from '@/components/ui/sidebar'
import { fetchAvailableWorkspaces } from '@/features/workspaces/api'
import { env } from '@/config/env'
import { buildAvailable, buildAvailableWorkspace, createWrapper } from '@/features/workspaces/test-support'

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

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 7 }, logout: vi.fn(), isAuthenticated: true }),
}))
vi.mock('@/features/workspaces/api', () => ({ fetchAvailableWorkspaces: vi.fn() }))
vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: [], isPending: false, isError: false }),
}))
vi.mock('@/features/absences/use-absence-menu-badge', () => ({ useAbsenceMenuBadge: () => null }))
vi.mock('@/features/notifications/use-unread-badge', () => ({
  useUnreadBadge: () => ({ count: 0, label: null, ariaLabel: '' }),
}))

function renderSidebar(path: string) {
  const { Wrapper } = createWrapper()
  render(
    <MemoryRouter initialEntries={[path]}>
      <SidebarProvider>
        <AppSidebar />
      </SidebarProvider>
    </MemoryRouter>,
    { wrapper: Wrapper },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  vi.mocked(fetchAvailableWorkspaces).mockResolvedValue(buildAvailable())
})

describe('AppSidebar inside an app (spec 0317 AC-018)', () => {
  it('shows the app name in the header and prefixes the fixed footer links', async () => {
    renderSidebar('/app/hr/absences')

    expect(await screen.findByText('Risorse umane')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Impostazioni/ })).toHaveAttribute('href', '/app/hr/settings')
    expect(screen.getByRole('link', { name: /Notifiche/ })).toHaveAttribute('href', '/app/hr/notifications')
  })

  it('AC-024: the app name sits above "QNet" in the app colour', async () => {
    renderSidebar('/app/hr/absences')

    const name = await screen.findByText('Risorse umane')
    const product = screen.getByText(env.appNameSidebar)
    expect(name.className).toContain('text-blue-300')
    expect(name.compareDocumentPosition(product) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })

  it('AC-024: an app without colour uses the neutral sidebar text class', async () => {
    vi.mocked(fetchAvailableWorkspaces).mockResolvedValue(
      buildAvailable({ workspaces: [buildAvailableWorkspace({ color: null })] }),
    )
    renderSidebar('/app/hr/absences')

    expect((await screen.findByText('Risorse umane')).className).toContain('text-sidebar-foreground')
  })

  it('badges the logo with the app icon inside an app, and not on the full view', async () => {
    renderSidebar('/app/hr/absences')

    const logo = document.querySelector('img[src="/brands/logo_white.svg"]')
    await screen.findByText('Risorse umane')
    expect(logo?.parentElement?.querySelector('svg')).not.toBeNull()
  })

  it('shows no app name on the full view', () => {
    renderSidebar('/absences')

    expect(screen.queryByText('Risorse umane')).not.toBeInTheDocument()
    expect(document.querySelector('img[src="/brands/logo_white.svg"]')?.parentElement?.querySelector('svg')).toBeNull()
    expect(screen.getByRole('link', { name: /Impostazioni/ })).toHaveAttribute('href', '/settings')
  })
})
