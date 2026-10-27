import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import i18n from '@/i18n'
import { WorkspaceLauncher } from '@/features/workspaces/components/workspace-launcher'
import { fetchAvailableWorkspaces } from '@/features/workspaces/api'
import {
  buildAvailable,
  buildAvailableWorkspace,
  createWrapper,
} from '@/features/workspaces/test-support'
import type { AvailableWorkspaces } from '@/features/workspaces/types'

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 7 }, isAuthenticated: true }),
}))
vi.mock('@/features/workspaces/api', () => ({ fetchAvailableWorkspaces: vi.fn() }))

const STORAGE_KEY = 'qnet.activeWorkspace.7'

function Probe() {
  const { pathname } = useLocation()
  return <p data-testid="location">{pathname}</p>
}

function renderLauncher(path: string, available: AvailableWorkspaces) {
  vi.mocked(fetchAvailableWorkspaces).mockResolvedValue(available)
  const { Wrapper } = createWrapper()
  return render(
    <MemoryRouter initialEntries={[path]}>
      <WorkspaceLauncher />
      <Routes>
        <Route path="*" element={<Probe />} />
      </Routes>
    </MemoryRouter>,
    { wrapper: Wrapper },
  )
}

const TWO_APPS = [
  buildAvailableWorkspace(),
  buildAvailableWorkspace({ slug: 'acc', name: 'Contabilita', icon: 'landmark', color: null, description: null, home_route: '/invoices' }),
]

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  window.localStorage.clear()
  vi.mocked(fetchAvailableWorkspaces).mockReset()
})

describe('WorkspaceLauncher (spec 0317 AC-017)', () => {
  it('lists the apps with the current one marked and no full view for a regular user', async () => {
    renderLauncher('/app/hr/absences', buildAvailable({ workspaces: TWO_APPS }))

    fireEvent.click(await screen.findByRole('button', { name: 'App' }))

    expect(screen.getByRole('link', { name: 'Risorse umane' })).toHaveAttribute('aria-current', 'true')
    expect(screen.getByRole('link', { name: 'Contabilita' })).not.toHaveAttribute('aria-current')
    expect(screen.queryByRole('link', { name: 'Vista completa' })).not.toBeInTheDocument()
  })

  it('AC-025: each app shows its icon before the name, in the app colour (neutral without colour)', async () => {
    renderLauncher('/app/hr/absences', buildAvailable({ workspaces: TWO_APPS }))

    fireEvent.click(await screen.findByRole('button', { name: 'App' }))

    const hr = screen.getByRole('link', { name: 'Risorse umane' })
    expect(hr.firstElementChild?.tagName.toLowerCase()).toBe('svg')
    expect(hr.className).toContain('text-blue-700')
    expect(hr.className).toContain('dark:text-blue-300')
    expect(screen.getByRole('link', { name: 'Contabilita' }).className).toContain('text-foreground')
  })

  it('AC-027: shows the description under the name when set, and nothing when null', async () => {
    renderLauncher('/app/hr/absences', buildAvailable({ workspaces: TWO_APPS }))

    fireEvent.click(await screen.findByRole('button', { name: 'App' }))

    const description = screen.getByText('Ferie, permessi e presenze')
    expect(screen.getByRole('link', { name: 'Risorse umane' })).toHaveAccessibleDescription('Ferie, permessi e presenze')
    expect(description.className).toContain('text-muted-foreground')
    expect(description.className).toContain('line-clamp-2')
    expect(screen.getByRole('link', { name: 'Contabilita' }).querySelectorAll('.line-clamp-2')).toHaveLength(0)
  })

  it('picking an app opens its home and remembers it', async () => {
    renderLauncher('/app/hr/absences', buildAvailable({ workspaces: TWO_APPS }))

    fireEvent.click(await screen.findByRole('button', { name: 'App' }))
    fireEvent.click(screen.getByRole('link', { name: 'Contabilita' }))

    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent('/app/acc/invoices'))
    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('acc')
  })

  it('offers the full view to a super-admin; choosing it forgets the app and opens /dashboard', async () => {
    window.localStorage.setItem(STORAGE_KEY, 'hr')
    renderLauncher('/app/hr/absences', buildAvailable({ full_view_available: true }))

    fireEvent.click(await screen.findByRole('button', { name: 'App' }))
    fireEvent.click(screen.getByRole('link', { name: 'Vista completa' }))

    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent(/^\/dashboard$/))
    expect(window.localStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('is not rendered with zero apps and the full view open to everyone (D-5)', async () => {
    renderLauncher('/dashboard', buildAvailable({ workspaces: [], full_view_available: true }))

    await waitFor(() => expect(fetchAvailableWorkspaces).toHaveBeenCalled())
    expect(screen.queryByRole('button', { name: 'App' })).not.toBeInTheDocument()
  })
})
