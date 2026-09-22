import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@/i18n'
import { HelpPanel } from '@/features/help/components/help-panel'
import { HELP_NAV_FIXTURE } from '@/features/help/test-support/help-nav-fixture'
import { buildFakeHelpGuide } from '@/features/help/test-support/help-guide-fixture'
import type { HelpGuide } from '@/features/help/types'
import type { HelpLocale } from '@/features/help/help-content-loader'

const GENERAL_GUIDE: HelpGuide = {
  key: 'general',
  title: 'Title general',
  summary: 'Introduzione.',
  sections: [
    {
      id: 'intro',
      title: 'Introduzione',
      blocks: [{ type: 'paragraph', text: 'Gestisci le telefonate in arrivo.' }],
    },
  ],
}

/** Spec 0143 rev 2, AC-015: "Questa pagina" / "Tutte le guide" tabs. */

vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: HELP_NAV_FIXTURE, isLoading: false }),
}))

const loadHelpGuideMock = vi.fn<(locale: HelpLocale, key: string) => Promise<HelpGuide | null>>()
vi.mock('@/features/help/help-content-loader', async () => {
  const actual = await vi.importActual<typeof import('@/features/help/help-content-loader')>(
    '@/features/help/help-content-loader',
  )
  return { ...actual, loadHelpGuide: (locale: HelpLocale, key: string) => loadHelpGuideMock(locale, key) }
})

function renderPanel(pathname = '/settings') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[pathname]}>
        <HelpPanel open onOpenChange={() => {}} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('HelpPanel tabs (AC-015)', () => {
  afterEach(() => {
    loadHelpGuideMock.mockReset()
  })

  it('exposes two accessible tabs, "Questa pagina" selected by default', async () => {
    loadHelpGuideMock.mockImplementation((_locale, key) => Promise.resolve(buildFakeHelpGuide(key)))
    renderPanel()

    const pageTab = screen.getByRole('tab', { name: 'Questa pagina' })
    const indexTab = screen.getByRole('tab', { name: 'Tutte le guide' })
    expect(pageTab).toHaveAttribute('aria-selected', 'true')
    expect(indexTab).toHaveAttribute('aria-selected', 'false')
    expect(await screen.findByRole('heading', { name: 'Title general' })).toBeInTheDocument()
  })

  it('switching to "Tutte le guide" shows the index and leaves the guide content unmounted', () => {
    loadHelpGuideMock.mockImplementation((_locale, key) => Promise.resolve(buildFakeHelpGuide(key)))
    renderPanel()

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Tutte le guide' }))

    expect(screen.getByRole('tab', { name: 'Tutte le guide' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('navigation', { name: 'Tutte le guide' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Title general' })).not.toBeInTheDocument()
  })

  it('selecting a search result switches back to "Questa pagina" with that guide (AC-005/AC-015)', async () => {
    loadHelpGuideMock.mockImplementation((_locale, key) =>
      Promise.resolve(key === 'general' ? GENERAL_GUIDE : buildFakeHelpGuide(key)),
    )
    const scrollIntoViewSpy = vi.spyOn(Element.prototype, 'scrollIntoView').mockImplementation(() => {})
    renderPanel()

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Tutte le guide' }))
    fireEvent.change(screen.getByRole('searchbox', { name: 'Cerca nelle guide' }), {
      target: { value: 'telefonate' },
    })

    const result = await screen.findByRole('button', { name: 'Title general › Introduzione' })
    fireEvent.click(result)

    expect(screen.getByRole('tab', { name: 'Questa pagina' })).toHaveAttribute('aria-selected', 'true')
    expect(await screen.findByRole('heading', { name: 'Title general' })).toBeInTheDocument()
    await waitFor(() => expect(scrollIntoViewSpy).toHaveBeenCalled())

    scrollIntoViewSpy.mockRestore()
  })
})
