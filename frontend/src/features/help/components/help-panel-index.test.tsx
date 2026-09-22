import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent, within } from '@testing-library/react'
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

/**
 * Spec 0143 AC-004 (visibility/grouping), AC-014 (icons) and AC-016
 * (collapsible groups, "Sei qui"). The former ghost button "Tutte le guide"
 * is now the second tab (rev 2): opening the index requires switching to it.
 */

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
  fireEvent.mouseDown(screen.getByRole('tab', { name: 'Tutte le guide' }))
}

describe('HelpPanel index', () => {
  afterEach(() => {
    navigationItems = HELP_NAV_FIXTURE
  })

  it('always shows "general" first, plus the grouped, visible module guides, groups collapsed by default (AC-004/AC-016)', () => {
    renderIndex()

    expect(screen.getByRole('button', { name: /Primi passi/ })).toBeInTheDocument()
    const adminGroup = screen.getByRole('button', { name: 'Amministrazione' })
    expect(adminGroup).toHaveAttribute('aria-expanded', 'false')
    expect(within(adminGroup).getByText('2')).toBeInTheDocument()
    // Collapsed: its guides are not in the accessibility tree yet.
    expect(screen.queryByRole('button', { name: 'Utenti' })).not.toBeInTheDocument()

    expect(screen.getByRole('button', { name: 'Opportunità e Commesse' })).toBeInTheDocument()
  })

  it('drops a guide whose menu entry is not present, but keeps "general"', () => {
    navigationItems = HELP_NAV_FIXTURE_WITHOUT_REQUEST_MANAGEMENT

    renderIndex()

    expect(screen.getByRole('button', { name: /Primi passi/ })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Opportunità e Commesse' })).not.toBeInTheDocument()
  })

  it('expanding a group reveals its guides with a module icon each (AC-014)', () => {
    renderIndex()

    fireEvent.click(screen.getByRole('button', { name: 'Amministrazione' }))

    const usersRow = screen.getByRole('button', { name: 'Utenti' })
    expect(usersRow.querySelector('svg')).not.toBeNull()
    expect(screen.getByRole('button', { name: 'Ruoli' })).toBeInTheDocument()
  })

  it('auto-expands the group holding the current module and marks it "Sei qui" (AC-016)', () => {
    renderIndex('/field-change-requests')

    const group = screen.getByRole('button', { name: 'Opportunità e Commesse' })
    expect(group).toHaveAttribute('aria-expanded', 'true')

    const currentRow = screen.getByRole('button', { name: 'Richieste di modifica Sei qui' })
    expect(currentRow).toHaveAttribute('aria-current', 'page')
    // The sibling module in the same group is not marked current.
    expect(screen.getByRole('button', { name: 'Gestione Richieste' })).not.toHaveAttribute('aria-current')
  })

  it('opening a guide from the index switches to the "this page" tab (AC-015)', async () => {
    renderIndex('/field-change-requests')

    fireEvent.click(screen.getByRole('button', { name: 'Gestione Richieste' }))

    expect(screen.getByRole('tab', { name: 'Questa pagina' })).toHaveAttribute('aria-selected', 'true')
    expect(await screen.findByRole('heading', { name: 'Title request-management' })).toBeInTheDocument()
  })
})
