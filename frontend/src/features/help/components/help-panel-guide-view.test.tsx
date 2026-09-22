import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@/i18n'
import { HelpPanel } from '@/features/help/components/help-panel'
import { HELP_NAV_FIXTURE } from '@/features/help/test-support/help-nav-fixture'
import type { HelpGuide } from '@/features/help/types'
import type { HelpLocale } from '@/features/help/help-content-loader'

/** Spec 0143 rev 2, AC-017: guide view header icon and clickable section index. */

vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: HELP_NAV_FIXTURE, isLoading: false }),
}))

const USERS_GUIDE: HelpGuide = {
  key: 'users',
  title: 'Utenti',
  summary: 'Gestione utenti.',
  sections: [
    { id: 'lista', title: 'Elenco', blocks: [{ type: 'paragraph', text: 'Filtra per ruolo.' }] },
    { id: 'creazione', title: 'Creazione', blocks: [{ type: 'paragraph', text: 'Compila il form.' }] },
  ],
}

const loadHelpGuideMock = vi.fn<(locale: HelpLocale, key: string) => Promise<HelpGuide | null>>()
vi.mock('@/features/help/help-content-loader', async () => {
  const actual = await vi.importActual<typeof import('@/features/help/help-content-loader')>(
    '@/features/help/help-content-loader',
  )
  return { ...actual, loadHelpGuide: (locale: HelpLocale, key: string) => loadHelpGuideMock(locale, key) }
})

function renderOnUsersPage() {
  loadHelpGuideMock.mockImplementation((_locale, key) => Promise.resolve(key === 'users' ? USERS_GUIDE : null))
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/users']}>
        <HelpPanel open onOpenChange={() => {}} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('HelpGuideView (AC-017)', () => {
  afterEach(() => {
    loadHelpGuideMock.mockReset()
  })

  it('shows the module icon next to the title, outside the heading', async () => {
    renderOnUsersPage()

    const heading = await screen.findByRole('heading', { name: 'Utenti' })
    expect(heading.querySelector('svg')).toBeNull()
    expect(heading.parentElement?.previousElementSibling?.tagName.toLowerCase()).toBe('svg')
  })

  it('lists sections as a clickable index that scrolls to the section', async () => {
    const scrollIntoViewSpy = vi.spyOn(Element.prototype, 'scrollIntoView').mockImplementation(() => {})
    renderOnUsersPage()

    await screen.findByRole('heading', { name: 'Utenti' })
    const sectionsIndex = screen.getByRole('list', { name: 'Sezioni della guida' })
    const secondSectionLink = within(sectionsIndex).getByRole('button', { name: 'Creazione' })

    fireEvent.click(secondSectionLink)

    await waitFor(() => expect(scrollIntoViewSpy).toHaveBeenCalledWith({ block: 'start' }))
    scrollIntoViewSpy.mockRestore()
  })
})
