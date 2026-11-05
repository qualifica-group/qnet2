import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, matchRoutes } from 'react-router-dom'
import i18n from '@/i18n'
import { AppBreadcrumbs } from '@/routes/breadcrumbs'
import { BreadcrumbTitleProvider } from '@/routes/breadcrumb-title'
import { router } from '@/routes/router'
import { navigation as itNavigation } from '@/i18n/locales/it-navigation'
import { navigation as enNavigation } from '@/i18n/locales/en-navigation'
import { permissions as itPermissions } from '@/i18n/locales/it-permissions'
import { permissions as enPermissions } from '@/i18n/locales/en-permissions'

vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: null }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const ROUTES = [
  { path: 'treasury-movements', label: 'Treasury movements' },
  { path: 'account-statements', label: 'Account statements' },
]

describe('treasury routes (spec 0360)', () => {
  it.each(ROUTES)('resolves /$path', ({ path }) => {
    const matches = matchRoutes(router.routes, { pathname: `/${path}` })
    expect(matches?.at(-1)?.route.path).toBe(path)
  })

  it.each(ROUTES)('shows the "$label" breadcrumb', async ({ path, label }) => {
    render(
      <MemoryRouter initialEntries={[`/${path}`]}>
        <BreadcrumbTitleProvider>
          <AppBreadcrumbs />
        </BreadcrumbTitleProvider>
      </MemoryRouter>,
    )
    expect(await screen.findByText(label)).toBeInTheDocument()
  })

  it('has menu and permission labels in IT and EN', () => {
    expect(itNavigation.treasuryMovements).toBe('Movimenti di tesoreria')
    expect(itNavigation.accountStatements).toBe('Estratto conto')
    expect(enNavigation.treasuryMovements).toBe('Treasury movements')
    expect(enNavigation.accountStatements).toBe('Account statements')
    for (const p of [itPermissions, enPermissions]) {
      expect(p.resources['treasury-movements']).toBeTruthy()
      expect(p.resources['treasury-types']).toBeTruthy()
      expect(p.abilities.manageOpeningBalance).toBeTruthy()
    }
  })
})
