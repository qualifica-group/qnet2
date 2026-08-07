import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestCreateForm } from '@/features/request-management/request-create-form'

/**
 * User directive 2026-07-31 ("la scheda di creazione il piu' simile possibile a
 * quella di gestione"): this suite pins the SKELETON the create form shares
 * with the work panel — the sticky identity bar carrying the save action,
 * the read-only side column (note generali + riepilogo) placed BEFORE the form
 * in the DOM, and the main column's section order. A later edit that quietly
 * drops one of them fails here.
 */

vi.mock('@/features/request-management/api', () => ({
  // The create form resolves its "Informazioni aggiuntive" from the picked
  // categories (user directive 2026-08-07): stubbed empty, this suite is not
  // about that block.
  fetchRequestFormContext: () => Promise.resolve({ applicable_attributes: [], attribute_layout: null }),
  createRequest: vi.fn(),
}))

// The attribution pickers and the registry picker read their options from the
// for-select endpoints; this suite is about layout, not about them.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

/**
 * No connected actor: the Operatore/Sede defaults (user directive 2026-08-04)
 * would otherwise seed both controls, which is not what this suite is about —
 * they have their own coverage in
 * `use-request-create-form-actor-defaults.test.ts`.
 */
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: null, isAuthenticated: true }),
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

describe('RequestCreateForm — lo scheletro del pannello', () => {
  it('mette il salvataggio nella barra sticky e lo ripete in fondo al form', () => {
    const { container } = renderCreateForm()

    const header = container.querySelector('header')
    expect(header).not.toBeNull()

    const saves = screen.getAllByRole('button', { name: 'Crea richiesta' })
    const cancels = screen.getAllByRole('button', { name: 'Annulla' })
    // Directive 2026-08-03: le stesse due azioni in testata e a piè di form.
    expect(saves).toHaveLength(2)
    expect(cancels).toHaveLength(2)

    // The panel's own bridge: both copies submit the form by id, never by DOM
    // nesting.
    saves.forEach((save) => expect(save).toHaveAttribute('form', 'request-create-form'))
    expect(header).toContainElement(saves[0])
    expect(header).toContainElement(cancels[0])
    expect(header).not.toContainElement(saves[1])
    expect(header).not.toContainElement(cancels[1])
  })

  /**
   * Directive 2026-08-03: una sola intestazione "Nuova richiesta", quella con
   * la descrizione sotto, e le azioni sulla sua destra. Il doppio titolo
   * nasceva dall'host (`ModuleFormPage`/SheetHeader) che ne rendeva un'altra:
   * il modulo ora e' registrato `formOwnsHeader` e questa barra e' l'unica.
   */
  it('rende una sola intestazione, con la descrizione e le azioni sulla stessa riga', () => {
    const { container } = renderCreateForm()

    const header = container.querySelector('header')
    const titles = screen.getAllByRole('heading', { name: 'Nuova richiesta' })
    expect(titles).toHaveLength(1)
    expect(header).toContainElement(titles[0])
    expect(header).toContainElement(
      screen.getByText('Anagrafica cliente e linee di prodotto della nuova richiesta.'),
    )
    expect(header).toContainElement(screen.getAllByRole('button', { name: 'Crea richiesta' })[0])
  })

  it('annulla dal fondo del form come dalla testata', () => {
    const onCancel = vi.fn()
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <ConfirmDialogProvider>
          <RequestCreateForm onSuccess={vi.fn()} onCancel={onCancel} />
        </ConfirmDialogProvider>
      </QueryClientProvider>,
    )

    fireEvent.click(screen.getAllByRole('button', { name: 'Annulla' })[1])

    expect(onCancel).toHaveBeenCalledTimes(1)
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
      'Linee di prodotto',
      'Prodotti di interesse',
      'Prossimo richiamo',
      'Attribuzione',
      'Anagrafica cliente',
    ])
  })

  /**
   * The panel's identity bar carries the record's pills; with nothing chosen
   * yet there is none to carry — they appear as the operator plans a callback
   * (their resolution is covered by the hook suites).
   */
  it('non mostra pillole nella barra finche non c\'e nulla da mostrare', () => {
    const { container } = renderCreateForm()

    const header = container.querySelector('header')
    expect(within(header!).queryByText('Prossimo richiamo')).not.toBeInTheDocument()
  })
})
