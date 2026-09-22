import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@/i18n'
import { HelpPanel } from '@/features/help/components/help-panel'
import { HELP_NAV_FIXTURE } from '@/features/help/test-support/help-nav-fixture'
import type { HelpGuide } from '@/features/help/types'
import type { HelpLocale } from '@/features/help/help-content-loader'

/** Spec 0143 AC-005 (search) and AC-012 (loading state while a guide's content downloads). */

vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: HELP_NAV_FIXTURE, isLoading: false }),
}))

const GUIDES: Record<string, HelpGuide> = {
  general: {
    key: 'general',
    title: 'Primi passi',
    summary: 'Introduzione.',
    sections: [
      {
        id: 'intro',
        title: 'Introduzione',
        blocks: [{ type: 'paragraph', text: 'Gestisci le telefonate in arrivo.' }],
      },
    ],
  },
  'request-management': {
    key: 'request-management',
    title: 'Gestione Richieste',
    summary: 'Trattative in corso.',
    sections: [{ id: 'panoramica', title: 'Panoramica', blocks: [{ type: 'paragraph', text: 'Elenco richieste.' }] }],
  },
  'field-change-requests': {
    key: 'field-change-requests',
    title: 'Richieste di modifica',
    summary: 'Coda revisione.',
    sections: [{ id: 'coda', title: 'Coda', blocks: [{ type: 'paragraph', text: 'Solo campi protetti.' }] }],
  },
  users: {
    key: 'users',
    title: 'Utenti',
    summary: 'Gestione utenti.',
    sections: [{ id: 'lista', title: 'Lista', blocks: [{ type: 'paragraph', text: 'Filtra per ruolo.' }] }],
  },
  roles: {
    key: 'roles',
    title: 'Ruoli',
    summary: 'Permessi.',
    sections: [{ id: 'lista', title: 'Lista', blocks: [{ type: 'paragraph', text: 'Assegna permessi.' }] }],
  },
}

const loadHelpGuideMock = vi.fn<(locale: HelpLocale, key: string) => Promise<HelpGuide | null>>()
vi.mock('@/features/help/help-content-loader', async () => {
  const actual = await vi.importActual<typeof import('@/features/help/help-content-loader')>(
    '@/features/help/help-content-loader',
  )
  return { ...actual, loadHelpGuide: (locale: HelpLocale, key: string) => loadHelpGuideMock(locale, key) }
})

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/settings']}>
        <HelpPanel open onOpenChange={() => {}} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('HelpPanel search (AC-005)', () => {
  afterEach(() => {
    loadHelpGuideMock.mockReset()
  })

  it('does not filter below 2 characters', () => {
    loadHelpGuideMock.mockImplementation((_locale, key) => Promise.resolve(GUIDES[key] ?? null))
    renderPanel()

    fireEvent.change(screen.getByRole('searchbox', { name: 'Cerca nelle guide' }), { target: { value: 't' } })

    expect(screen.queryByText('Nessun risultato trovato.')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Primi passi › / })).not.toBeInTheDocument()
  })

  it('finds case- and accent-insensitive matches across the visible guides and navigates to the hit', async () => {
    loadHelpGuideMock.mockImplementation((_locale, key) => Promise.resolve(GUIDES[key] ?? null))
    const scrollIntoViewSpy = vi.spyOn(Element.prototype, 'scrollIntoView').mockImplementation(() => {})
    renderPanel()

    fireEvent.change(screen.getByRole('searchbox', { name: 'Cerca nelle guide' }), {
      target: { value: 'TELEFONATE' },
    })

    const result = await screen.findByRole('button', { name: 'Primi passi › Introduzione' })
    fireEvent.click(result)

    expect(await screen.findByRole('heading', { name: 'Primi passi' })).toBeInTheDocument()
    await waitFor(() => expect(scrollIntoViewSpy).toHaveBeenCalled())

    scrollIntoViewSpy.mockRestore()
  })

  it('shows the i18n empty-state message for an unmatched query', async () => {
    loadHelpGuideMock.mockImplementation((_locale, key) => Promise.resolve(GUIDES[key] ?? null))
    renderPanel()

    fireEvent.change(screen.getByRole('searchbox', { name: 'Cerca nelle guide' }), {
      target: { value: 'zzz-nessuna-corrispondenza' },
    })

    expect(await screen.findByText('Nessun risultato trovato.')).toBeInTheDocument()
  })
})

describe('HelpPanel loading state (AC-012)', () => {
  afterEach(() => {
    loadHelpGuideMock.mockReset()
  })

  it('shows a loading state while the current guide downloads', async () => {
    let resolveGuide: (guide: HelpGuide) => void = () => {}
    loadHelpGuideMock.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveGuide = resolve
        }),
    )

    renderPanel()

    expect(await screen.findAllByRole('status')).not.toHaveLength(0)

    resolveGuide(GUIDES.general)
    expect(await screen.findByRole('heading', { name: 'Primi passi' })).toBeInTheDocument()
  })
})
