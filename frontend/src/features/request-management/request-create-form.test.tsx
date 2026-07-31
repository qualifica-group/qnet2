import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestCreateForm } from '@/features/request-management/request-create-form'
import { EMPTY_FORM_CONTEXT } from '@/features/request-management/request-create-form-harness'
import type { RequestFormContext } from '@/features/request-management/types'

/**
 * User directive 2026-07-31 ("la scheda di creazione il piu' simile possibile a
 * quella di gestione"): this suite pins the SKELETON the create form shares
 * with the work panel — the sticky identity bar carrying the only save action,
 * the read-only side column (note generali + riepilogo) placed BEFORE the form
 * in the DOM, and the main column's section order. A later edit that quietly
 * drops one of them fails here.
 */

const fetchRequestFormContextMock = vi.fn(async (): Promise<RequestFormContext> => EMPTY_FORM_CONTEXT)
vi.mock('@/features/request-management/api', () => ({
  createRequest: vi.fn(),
  fetchRequestFormContext: () => fetchRequestFormContextMock(),
}))

// The attribution pickers and the registry picker read their options from the
// for-select endpoints; this suite is about layout, not about them.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderCreateForm() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestCreateForm onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchRequestFormContextMock.mockReset()
  fetchRequestFormContextMock.mockResolvedValue(EMPTY_FORM_CONTEXT)
})

describe('RequestCreateForm — lo scheletro del pannello', () => {
  it('mette il salvataggio nella barra sticky, non in fondo alla pagina', () => {
    const { container } = renderCreateForm()

    const header = container.querySelector('header')
    expect(header).not.toBeNull()

    const save = screen.getByRole('button', { name: 'Crea richiesta' })
    // The panel's own bridge: the button lives in the header and submits the
    // form by id, never by DOM nesting.
    expect(save).toHaveAttribute('form', 'request-create-form')
    expect(header).toContainElement(save)
    expect(header).toContainElement(screen.getByRole('button', { name: 'Annulla' }))
  })

  it('rende la colonna laterale (note generali + riepilogo) PRIMA del form nel DOM', () => {
    const { container } = renderCreateForm()

    const aside = container.querySelector('aside')
    const form = container.querySelector('form')
    expect(aside).not.toBeNull()
    expect(form).not.toBeNull()
    expect(aside!.compareDocumentPosition(form!) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()

    // Same callout the panel reads the notes from, here as an editable field.
    expect(aside).toContainElement(screen.getByRole('textbox', { name: 'Note generali' }))
    expect(aside).toContainElement(screen.getByText('Riepilogo richiesta'))
  })

  it('ordina le sezioni della colonna principale come il pannello', () => {
    renderCreateForm()

    const order = screen
      .getAllByRole('heading', { level: 3 })
      .map((heading) => heading.textContent)
      .filter((title): title is string =>
        [
          'Prossimo richiamo',
          'Attribuzione',
          'Linee di prodotto',
          'Prodotti di interesse',
          'Anagrafica cliente',
        ].includes(title ?? ''),
      )

    expect(order).toEqual([
      'Prossimo richiamo',
      'Attribuzione',
      'Linee di prodotto',
      'Prodotti di interesse',
      'Anagrafica cliente',
    ])
  })

  /**
   * Both are resolved FROM the chosen categoria prodotto: with none picked
   * there is nothing to offer, and an empty card would read as "this request
   * has no working status / no additional fields".
   */
  it('non mostra stato di lavorazione e campi dinamici finche non ci sono criteri', async () => {
    renderCreateForm()

    await waitFor(() => expect(fetchRequestFormContextMock).not.toHaveBeenCalled())
    expect(screen.queryByText('Stato di lavorazione')).not.toBeInTheDocument()
    expect(screen.queryByText('Informazioni aggiuntive')).not.toBeInTheDocument()
  })

  /**
   * The panel's identity bar carries the record's pills; with nothing chosen
   * yet there is none to carry — they appear as the operator picks a status or
   * plans a callback (their resolution is covered by the hook suites).
   */
  it('non mostra pillole nella barra finche non c\'e nulla da mostrare', () => {
    const { container } = renderCreateForm()

    const header = container.querySelector('header')
    expect(within(header!).queryByText('Lavorazione')).not.toBeInTheDocument()
    expect(within(header!).queryByText('Prossimo richiamo')).not.toBeInTheDocument()
  })
})
