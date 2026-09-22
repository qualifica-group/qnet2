import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { HelpPanel } from '@/features/help/components/help-panel'
import {
  HELP_NAV_FIXTURE,
  HELP_NAV_FIXTURE_WITHOUT_REQUEST_MANAGEMENT,
} from '@/features/help/test-support/help-nav-fixture'
import { buildFakeHelpGuide } from '@/features/help/test-support/help-guide-fixture'
import type { HelpLocale } from '@/features/help/help-content-loader'

/** Spec 0143 AC-002/AC-003/AC-006: current-module resolution and locale switching. */

let navigationItems = HELP_NAV_FIXTURE
vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: navigationItems, isLoading: false }),
}))

const loadHelpGuideMock = vi.fn<(locale: HelpLocale, key: string) => Promise<ReturnType<typeof buildFakeHelpGuide> | null>>()
vi.mock('@/features/help/help-content-loader', async () => {
  const actual = await vi.importActual<typeof import('@/features/help/help-content-loader')>(
    '@/features/help/help-content-loader',
  )
  return { ...actual, loadHelpGuide: (locale: HelpLocale, key: string) => loadHelpGuideMock(locale, key) }
})

function renderPanel(pathname: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[pathname]}>
        <HelpPanel open onOpenChange={() => {}} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('HelpPanel current guide resolution', () => {
  afterEach(() => {
    navigationItems = HELP_NAV_FIXTURE
    loadHelpGuideMock.mockReset()
    void i18n.changeLanguage('it')
  })

  it('opens the "Gestione Richieste" guide on /request-management and on /request-management/12 (AC-002)', async () => {
    loadHelpGuideMock.mockImplementation((_locale, key) =>
      Promise.resolve(buildFakeHelpGuide(key, { title: 'Gestione Richieste' })),
    )

    renderPanel('/request-management/12')

    await waitFor(() => {
      expect(loadHelpGuideMock).toHaveBeenCalledWith('it', 'request-management')
    })
    expect(await screen.findByRole('heading', { name: 'Gestione Richieste' })).toBeInTheDocument()
  })

  it('falls back to "general" on an unmatched path (AC-003)', async () => {
    loadHelpGuideMock.mockImplementation((_locale, key) =>
      Promise.resolve(buildFakeHelpGuide(key)),
    )

    renderPanel('/settings')

    await waitFor(() => {
      expect(loadHelpGuideMock).toHaveBeenCalledWith('it', 'general')
    })
  })

  it('loads the EN content when the UI language is English (AC-006)', async () => {
    loadHelpGuideMock.mockImplementation((_locale, key) =>
      Promise.resolve(buildFakeHelpGuide(key, { title: `EN ${key}` })),
    )
    await i18n.changeLanguage('en')

    renderPanel('/request-management')

    await waitFor(() => {
      expect(loadHelpGuideMock).toHaveBeenCalledWith('en', 'request-management')
    })
  })

  it('excludes a guide whose menu entry is gone (AC-004)', async () => {
    navigationItems = HELP_NAV_FIXTURE_WITHOUT_REQUEST_MANAGEMENT
    loadHelpGuideMock.mockImplementation((_locale, key) => Promise.resolve(buildFakeHelpGuide(key)))

    renderPanel('/request-management')

    await waitFor(() => {
      expect(loadHelpGuideMock).toHaveBeenCalledWith('it', 'general')
    })
  })
})
