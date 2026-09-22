import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@/i18n'
import { HelpPanel } from '@/features/help/components/help-panel'
import {
  HELP_NAV_FIXTURE,
  HELP_NAV_FIXTURE_WITHOUT_REQUEST_MANAGEMENT,
} from '@/features/help/test-support/help-nav-fixture'
import { buildFakeHelpGuide } from '@/features/help/test-support/help-guide-fixture'
import type { HelpLocale } from '@/features/help/help-content-loader'

/** Spec 0143 AC-004: the index lists only visible guides, grouped, "general" always first. */

let navigationItems = HELP_NAV_FIXTURE
vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: navigationItems, isLoading: false }),
}))

vi.mock('@/features/help/help-content-loader', async () => {
  const actual = await vi.importActual<typeof import('@/features/help/help-content-loader')>(
    '@/features/help/help-content-loader',
  )
  return {
    ...actual,
    loadHelpGuide: (_locale: HelpLocale, key: string) => Promise.resolve(buildFakeHelpGuide(key)),
  }
})

function renderIndex(pathname = '/settings') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[pathname]}>
        <HelpPanel open onOpenChange={() => {}} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
  fireEvent.click(screen.getByRole('button', { name: 'Tutte le guide' }))
}

describe('HelpPanel index', () => {
  afterEach(() => {
    navigationItems = HELP_NAV_FIXTURE
  })

  it('always shows "general" first, plus the grouped, visible module guides', () => {
    renderIndex()

    expect(screen.getByRole('button', { name: 'Primi passi' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Gestione Richieste' })).toBeInTheDocument()
    expect(screen.getByText('Opportunità e Commesse')).toBeInTheDocument()
  })

  it('drops a guide whose menu entry is not present, but keeps "general"', () => {
    navigationItems = HELP_NAV_FIXTURE_WITHOUT_REQUEST_MANAGEMENT

    renderIndex()

    expect(screen.getByRole('button', { name: 'Primi passi' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Gestione Richieste' })).not.toBeInTheDocument()
  })

  it('opening a guide from the index leaves the index view', async () => {
    renderIndex()

    fireEvent.click(screen.getByRole('button', { name: 'Gestione Richieste' }))

    expect(screen.queryByRole('button', { name: 'Primi passi' })).not.toBeInTheDocument()
    expect(await screen.findByRole('heading', { name: 'Title request-management' })).toBeInTheDocument()
  })
})
