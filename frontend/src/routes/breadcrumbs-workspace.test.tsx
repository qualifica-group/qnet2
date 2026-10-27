import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { AppBreadcrumbs } from '@/routes/breadcrumbs'
import { BreadcrumbTitleProvider, useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { fetchAvailableWorkspaces } from '@/features/workspaces/api'
import { buildAvailable, createWrapper } from '@/features/workspaces/test-support'

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 7 }, isAuthenticated: true }),
}))
vi.mock('@/features/workspaces/api', () => ({ fetchAvailableWorkspaces: vi.fn() }))
vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: null }),
}))

function DetailPageStub({ href, name }: { href: string; name: string }) {
  useBreadcrumbTitle(href, name)
  return null
}

function renderAt(path: string, page: React.ReactNode = null) {
  const { Wrapper } = createWrapper()
  return render(
    <MemoryRouter initialEntries={[path]}>
      <BreadcrumbTitleProvider>
        <AppBreadcrumbs />
        {page}
      </BreadcrumbTitleProvider>
    </MemoryRouter>,
    { wrapper: Wrapper },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  vi.mocked(fetchAvailableWorkspaces).mockReset()
  vi.mocked(fetchAvailableWorkspaces).mockResolvedValue(buildAvailable())
})

describe('AppBreadcrumbs inside an app (spec 0317 AC-018)', () => {
  it('resolves the page like the unprefixed URL and starts with the app name linking to its home', async () => {
    renderAt('/app/hr/invoices')

    expect(await screen.findByRole('link', { name: 'Risorse umane' })).toHaveAttribute('href', '/app/hr')
    expect(screen.getByText('Fatture Attive')).toBeInTheDocument()
    expect(screen.queryByText('Hr')).not.toBeInTheDocument()
  })

  it('keeps the entity title registered under the unprefixed path and prefixes the crumb links', async () => {
    renderAt('/app/hr/registries/12', <DetailPageStub href="/registries/12" name="Acme S.p.A." />)

    expect(await screen.findByText('Acme S.p.A.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Anagrafiche' })).toHaveAttribute('href', '/app/hr/registries')
  })

  it('shows only the app name on the app root', async () => {
    renderAt('/app/hr')

    expect(await screen.findByText('Risorse umane')).toBeInTheDocument()
    expect(screen.getByText('Risorse umane')).not.toHaveAttribute('href')
  })

  it('adds no app crumb outside an app', () => {
    renderAt('/invoices')

    expect(screen.getByText('Fatture Attive')).toBeInTheDocument()
    expect(fetchAvailableWorkspaces).not.toHaveBeenCalled()
  })
})
