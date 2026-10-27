import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import WorkspacesPage from '@/features/workspaces/workspaces-page'
import {
  createWorkspace,
  deleteWorkspace,
  fetchNavigationCatalogue,
  fetchWorkspaces,
  updateWorkspace,
} from '@/features/workspaces/api'
import { buildWorkspace, createWrapper } from '@/features/workspaces/test-support'
import type { NavigationCatalogueEntry } from '@/features/workspaces/types'

const confirmMock = vi.hoisted(() => vi.fn())

vi.mock('@/routes/breadcrumbs', () => ({ AppBreadcrumbs: () => null }))
vi.mock('@/components/confirm-dialog-context', () => ({ useConfirm: () => confirmMock }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/workspaces/api', () => ({
  fetchWorkspaces: vi.fn(),
  fetchNavigationCatalogue: vi.fn(),
  createWorkspace: vi.fn(),
  updateWorkspace: vi.fn(),
  deleteWorkspace: vi.fn(),
}))

const CATALOGUE: NavigationCatalogueEntry[] = [
  { key: 'absences', label: 'navigation.absences', icon: null, route: '/absences', path: ['navigation.management'] },
  { key: 'invoices', label: 'navigation.invoices', icon: null, route: '/invoices', path: ['navigation.administration'] },
]

function unprocessable(errors: Record<string, string[]>): AxiosError {
  return new AxiosError('Unprocessable', 'ERR_BAD_REQUEST', undefined, undefined, {
    status: 422,
    data: { success: false, message: 'Invalid', errors },
  } as AxiosResponse)
}

function renderPage() {
  const { Wrapper, client } = createWrapper()
  const invalidate = vi.spyOn(client, 'invalidateQueries')
  render(
    <MemoryRouter>
      <WorkspacesPage />
    </MemoryRouter>,
    { wrapper: Wrapper },
  )
  return { invalidate }
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  vi.resetAllMocks()
  vi.mocked(fetchWorkspaces).mockResolvedValue([buildWorkspace()])
  vi.mocked(fetchNavigationCatalogue).mockResolvedValue(CATALOGUE)
})

async function fillNewApp() {
  fireEvent.click(await screen.findByRole('button', { name: 'Nuova app' }))
  const dialog = await screen.findByRole('dialog')
  fireEvent.change(within(dialog).getByLabelText(/Nome/), { target: { value: 'Contabilita' } })
  fireEvent.change(within(dialog).getByLabelText(/Indirizzo/), { target: { value: 'acc' } })
  return dialog
}

describe('WorkspacesPage (spec 0317 AC-019)', () => {
  it('lists the configured apps', async () => {
    renderPage()

    expect(await screen.findByText('Risorse umane')).toBeInTheDocument()
    expect(screen.getByText(/\/app\/hr/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Modifica Risorse umane' })).toBeInTheDocument()
  })

  it('creates an app from the grouped entry checkboxes and refreshes workspaces and navigation', async () => {
    vi.mocked(createWorkspace).mockResolvedValue(buildWorkspace({ id: 2, slug: 'acc' }))
    const { invalidate } = renderPage()

    const dialog = await fillNewApp()
    expect(await within(dialog).findByText('Amministrazione')).toBeInTheDocument()
    fireEvent.click(await within(dialog).findByRole('checkbox', { name: 'Fatture Attive' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    await waitFor(() =>
      expect(createWorkspace).toHaveBeenCalledWith({
        name: 'Contabilita',
        slug: 'acc',
        description: null,
        icon: null,
        color: null,
        sort_order: 0,
        navigation_keys: ['invoices'],
      }),
    )
    await waitFor(() => {
      expect(invalidate).toHaveBeenCalledWith({ queryKey: ['workspaces'] })
      expect(invalidate).toHaveBeenCalledWith({ queryKey: ['navigation'] })
    })
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('AC-023: icon and colour are chosen from the pickers and end up in the payload', async () => {
    vi.mocked(createWorkspace).mockResolvedValue(buildWorkspace({ id: 2, slug: 'acc' }))
    renderPage()

    const dialog = await fillNewApp()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Icona' }))
    fireEvent.change(screen.getByPlaceholderText('Cerca icona…'), { target: { value: 'landmark' } })
    fireEvent.click(screen.getByRole('option', { name: 'landmark' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Scegli un colore…' }))
    fireEvent.click(screen.getByRole('option', { name: 'Blu' }))
    fireEvent.click(await within(dialog).findByRole('checkbox', { name: 'Fatture Attive' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    await waitFor(() =>
      expect(createWorkspace).toHaveBeenCalledWith(
        expect.objectContaining({ icon: 'landmark', color: 'blue' }),
      ),
    )
  })

  it('AC-027: the optional description is sent trimmed, and over 160 characters is rejected on the field', async () => {
    vi.mocked(createWorkspace).mockResolvedValue(buildWorkspace({ id: 2, slug: 'acc' }))
    renderPage()

    const dialog = await fillNewApp()
    const field = within(dialog).getByLabelText('Descrizione')
    fireEvent.change(field, { target: { value: 'x'.repeat(161) } })
    expect(within(dialog).getByText('161/160 caratteri. Facoltativa: compare sotto il nome nel launcher.')).toBeInTheDocument()
    fireEvent.click(await within(dialog).findByRole('checkbox', { name: 'Fatture Attive' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    const alert = await within(dialog).findByText('La descrizione può avere al massimo 160 caratteri.')
    expect(alert).toHaveAttribute('role', 'alert')
    expect(field).toHaveAttribute('aria-invalid', 'true')
    expect(createWorkspace).not.toHaveBeenCalled()

    fireEvent.change(field, { target: { value: '  Contabilità e fatture  ' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    await waitFor(() =>
      expect(createWorkspace).toHaveBeenCalledWith(
        expect.objectContaining({ description: 'Contabilità e fatture' }),
      ),
    )
  })

  it('edits an app with its current values and sends the replaced entry set', async () => {
    vi.mocked(updateWorkspace).mockResolvedValue(buildWorkspace())
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: 'Modifica Risorse umane' }))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByLabelText(/Nome/)).toHaveValue('Risorse umane')
    fireEvent.click(await within(dialog).findByRole('checkbox', { name: 'Fatture Attive' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    await waitFor(() =>
      expect(updateWorkspace).toHaveBeenCalledWith(
        1,
        expect.objectContaining({ slug: 'hr', navigation_keys: ['absences', 'invoices'] }),
      ),
    )
  })

  it('validates client-side before calling the server', async () => {
    renderPage()

    const dialog = await fillNewApp()
    fireEvent.change(within(dialog).getByLabelText(/Indirizzo/), { target: { value: 'Bad Slug' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    expect(await within(dialog).findByText('Usa lettere minuscole, numeri e trattini singoli.')).toBeInTheDocument()
    expect(within(dialog).getByText('Seleziona almeno una voce di menu.')).toBeInTheDocument()
    expect(createWorkspace).not.toHaveBeenCalled()
  })

  it('shows a 422 on the slug field with the accessible error wiring', async () => {
    vi.mocked(createWorkspace).mockRejectedValue(unprocessable({ slug: ['Questo indirizzo è già in uso.'] }))
    renderPage()

    const dialog = await fillNewApp()
    fireEvent.click(await within(dialog).findByRole('checkbox', { name: 'Fatture Attive' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    const alert = await within(dialog).findByText('Questo indirizzo è già in uso.')
    expect(alert).toHaveAttribute('role', 'alert')
    const slugField = within(dialog).getByLabelText(/Indirizzo/)
    expect(slugField).toHaveAttribute('aria-invalid', 'true')
    expect(slugField.getAttribute('aria-describedby')).toContain(alert.id)
  })

  it('fills the address from the name until it is typed by hand', async () => {
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: 'Nuova app' }))
    const dialog = await screen.findByRole('dialog')
    const name = within(dialog).getByLabelText(/Nome/)
    const slug = within(dialog).getByLabelText(/Indirizzo/)
    fireEvent.change(name, { target: { value: 'Qualifica Contabilità' } })
    expect(slug).toHaveValue('qualifica-contabilita')

    fireEvent.change(slug, { target: { value: 'acc' } })
    fireEvent.change(name, { target: { value: 'Contabilità generale' } })
    expect(slug).toHaveValue('acc')
  })

  it('selects a whole section from its checkbox and narrows the entries with the search', async () => {
    vi.mocked(createWorkspace).mockResolvedValue(buildWorkspace({ id: 2, slug: 'acc' }))
    renderPage()

    const dialog = await fillNewApp()
    fireEvent.change(await within(dialog).findByRole('searchbox', { name: 'Cerca voci di menu' }), {
      target: { value: 'fatt' },
    })
    expect(within(dialog).queryByRole('checkbox', { name: 'Assenze' })).not.toBeInTheDocument()
    fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Amministrazione' }))
    expect(within(dialog).getByText('1 selezionata')).toBeInTheDocument()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    await waitFor(() =>
      expect(createWorkspace).toHaveBeenCalledWith(expect.objectContaining({ navigation_keys: ['invoices'] })),
    )
  })

  it('asks before closing with unsaved changes and stays open when declined', async () => {
    confirmMock.mockResolvedValue(false)
    renderPage()

    const dialog = await fillNewApp()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Annulla' }))

    await waitFor(() => expect(confirmMock).toHaveBeenCalledWith(expect.objectContaining({ tone: 'warning' })))
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('asks for confirmation before deleting and does nothing when declined', async () => {
    confirmMock.mockResolvedValue(false)
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: 'Elimina Risorse umane' }))

    await waitFor(() => expect(confirmMock).toHaveBeenCalled())
    expect(deleteWorkspace).not.toHaveBeenCalled()
  })

  it('deletes the app once confirmed and refreshes workspaces and navigation', async () => {
    confirmMock.mockResolvedValue(true)
    vi.mocked(deleteWorkspace).mockResolvedValue(undefined)
    const { invalidate } = renderPage()

    fireEvent.click(await screen.findByRole('button', { name: 'Elimina Risorse umane' }))

    await waitFor(() => expect(deleteWorkspace).toHaveBeenCalledWith(1))
    await waitFor(() => {
      expect(invalidate).toHaveBeenCalledWith({ queryKey: ['workspaces'] })
      expect(invalidate).toHaveBeenCalledWith({ queryKey: ['navigation'] })
    })
  })
})
