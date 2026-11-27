import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { buildRequest, httpError } from '@/features/document-requests/document-request-fixtures'
import { DocumentRequestsSection } from '@/features/document-requests/document-requests-section'
import type { DocumentRequestList } from '@/features/document-requests/types'

const fetchListMock = vi.fn<(workOrderId: number) => Promise<DocumentRequestList>>()
const runActionMock = vi.fn<(id: number, action: string) => Promise<void>>()
const fetchLogsMock = vi.fn()
vi.mock('@/features/document-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/document-requests/api')>()),
  fetchDocumentRequests: (id: number) => fetchListMock(id),
  runDocumentRequestAction: (id: number, action: string) => runActionMock(id, action),
  fetchDocumentRequestSyncLogs: (id: number) => fetchLogsMock(id),
}))

function renderSection() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const Wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
  return render(<DocumentRequestsSection workOrderId={5} />, { wrapper: Wrapper })
}

describe('DocumentRequestsSection', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    runActionMock.mockResolvedValue(undefined)
  })

  it('lists a request with status label, satisfied/total bar and items per group with reason and files (AC-017)', async () => {
    fetchListMock.mockResolvedValue({ requests: [buildRequest()], canCreate: true })
    renderSection()

    const card = await screen.findByRole('article', { name: 'Documenti societari - ACME' })
    expect(within(card).getByText('In corso')).toBeInTheDocument()
    expect(within(card).getByText('1 / 2 voci')).toBeInTheDocument()
    expect(within(card).getByRole('progressbar', { name: 'Voci soddisfatte' })).toBeInTheDocument()

    const group = within(card).getByRole('region', { name: 'Societa' })
    expect(within(group).getByText('Visura camerale')).toBeInTheDocument()
    expect(within(group).getByText('Approvato')).toBeInTheDocument()
    expect(within(group).getByText('Da correggere')).toBeInTheDocument()
    expect(within(group).getByText(/Motivo: Documento illeggibile/)).toBeInTheDocument()
    expect(within(group).getByText('2 file')).toBeInTheDocument()

    const ungrouped = within(card).getByRole('region', { name: 'Altri documenti' })
    expect(within(ungrouped).getByText('Delega')).toBeInTheDocument()
    expect(within(ungrouped).getByText('Da caricare')).toBeInTheDocument()
    expect(within(card).getByText('cliente@example.com')).toBeInTheDocument()
  })

  it('shows the queued send and the failed send with the ArchivioSmart message (AC-017)', async () => {
    fetchListMock.mockResolvedValue({
      requests: [
        buildRequest({ id: 11, title: 'Coda', status: null, delivery_status: 'pending', pending_operation: 'upsert' }),
        buildRequest({ id: 12, title: 'Errore', status: null, delivery_status: 'failed', delivery_error: 'Checklist non trovata' }),
      ],
      canCreate: false,
    })
    renderSection()

    const queued = await screen.findByRole('article', { name: 'Coda' })
    expect(within(queued).getByRole('status')).toHaveTextContent('In attesa di invio')
    expect(within(queued).getByText('In attesa di risposta')).toBeInTheDocument()

    const failed = screen.getByRole('article', { name: 'Errore' })
    expect(within(failed).getByRole('alert')).toHaveTextContent('Invio non riuscito: Checklist non trovata')
  })

  it('shows a closed request collapsed and toggles it with one click, while an open one stays expanded', async () => {
    fetchListMock.mockResolvedValue({
      requests: [
        buildRequest({ id: 31, title: 'Aperta' }),
        buildRequest({ id: 32, title: 'Chiusa', status: 'completed' }),
      ],
      canCreate: false,
    })
    renderSection()

    const closed = await screen.findByRole('article', { name: 'Chiusa' })
    const toggle = within(closed).getByRole('button', { name: 'Chiusa' })
    expect(toggle).toHaveAttribute('aria-expanded', 'false')
    expect(within(closed).getByText('1 / 2 voci')).toBeInTheDocument()
    expect(within(closed).queryByText('Visura camerale')).not.toBeInTheDocument()

    fireEvent.click(toggle)
    expect(toggle).toHaveAttribute('aria-expanded', 'true')
    expect(within(closed).getByText('Visura camerale')).toBeInTheDocument()

    fireEvent.click(toggle)
    expect(within(closed).queryByText('Visura camerale')).not.toBeInTheDocument()

    const open = screen.getByRole('article', { name: 'Aperta' })
    expect(within(open).queryByRole('button', { name: 'Aperta' })).not.toBeInTheDocument()
    expect(within(open).getByText('Visura camerale')).toBeInTheDocument()
  })

  it('offers the actions allowed by the state and the permissions (AC-017)', async () => {
    fetchListMock.mockResolvedValue({
      requests: [
        buildRequest({ id: 21, title: 'Aperta' }),
        buildRequest({ id: 22, title: 'Completata', status: 'completed' }),
        buildRequest({ id: 23, title: 'Bozza', status: 'draft' }),
        buildRequest({ id: 24, title: 'Sola lettura', permissions: { update: false, remind: false, cancel: false, review: false, complete: false, refresh_rules: false } }),
      ],
      canCreate: false,
    })
    renderSection()

    const open = await screen.findByRole('article', { name: 'Aperta' })
    for (const name of ['Modifica', 'Sollecita', 'Aggiorna stato', 'Annulla richiesta', 'Sincronizzazioni']) {
      expect(within(open).getByRole('button', { name })).toBeInTheDocument()
    }

    const completed = screen.getByRole('article', { name: 'Completata' })
    fireEvent.click(within(completed).getByRole('button', { name: 'Completata' }))
    expect(within(completed).queryByRole('button', { name: 'Modifica' })).not.toBeInTheDocument()
    expect(within(completed).queryByRole('button', { name: 'Sollecita' })).not.toBeInTheDocument()
    expect(within(completed).queryByRole('button', { name: 'Annulla richiesta' })).not.toBeInTheDocument()
    expect(within(completed).getByRole('button', { name: 'Aggiorna stato' })).toBeInTheDocument()

    const draft = screen.getByRole('article', { name: 'Bozza' })
    expect(within(draft).queryByRole('button', { name: 'Sollecita' })).not.toBeInTheDocument()
    expect(within(draft).getByRole('button', { name: 'Modifica' })).toBeInTheDocument()

    const readOnly = screen.getByRole('article', { name: 'Sola lettura' })
    expect(within(readOnly).queryByRole('button', { name: /Modifica|Sollecita|Aggiorna stato|Annulla richiesta/ })).not.toBeInTheDocument()
    expect(within(readOnly).getByRole('button', { name: 'Sincronizzazioni' })).toBeInTheDocument()
  })

  it('hides "Richiedi documenti" without can_create and shows it with it', async () => {
    fetchListMock.mockResolvedValue({ requests: [], canCreate: false })
    const { unmount } = renderSection()
    expect(await screen.findByText('Nessuna richiesta di documenti per questa commessa.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Richiedi documenti' })).not.toBeInTheDocument()
    unmount()

    fetchListMock.mockResolvedValue({ requests: [], canCreate: true })
    renderSection()
    expect(await screen.findByRole('button', { name: 'Richiedi documenti' })).toBeInTheDocument()
  })

  it('cancels only after confirmation and then calls the endpoint (AC-019)', async () => {
    fetchListMock.mockResolvedValue({ requests: [buildRequest()], canCreate: false })
    renderSection()

    fireEvent.click(await screen.findByRole('button', { name: 'Annulla richiesta' }))
    const dialog = await screen.findByRole('alertdialog')
    expect(runActionMock).not.toHaveBeenCalled()

    fireEvent.click(within(dialog).getByRole('button', { name: 'Mantieni' }))
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(runActionMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Annulla richiesta' }))
    fireEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Annulla richiesta' }))

    await waitFor(() => expect(runActionMock).toHaveBeenCalledWith(10, 'cancel'))
  })

  it('reminds and refreshes through the endpoints and rereads the list (AC-019)', async () => {
    fetchListMock.mockResolvedValue({ requests: [buildRequest()], canCreate: false })
    renderSection()

    fireEvent.click(await screen.findByRole('button', { name: 'Sollecita' }))
    await waitFor(() => expect(runActionMock).toHaveBeenCalledWith(10, 'remind'))
    await waitFor(() => expect(fetchListMock).toHaveBeenCalledTimes(2))

    fireEvent.click(screen.getByRole('button', { name: 'Aggiorna stato' }))
    await waitFor(() => expect(runActionMock).toHaveBeenCalledWith(10, 'refresh'))
  })

  it('does not break when an action is refused with a 422 on status (AC-019)', async () => {
    fetchListMock.mockResolvedValue({ requests: [buildRequest()], canCreate: false })
    runActionMock.mockRejectedValue(httpError(422, { message: 'Errore', errors: { status: ['Richiesta gia completata'] } }))
    renderSection()

    fireEvent.click(await screen.findByRole('button', { name: 'Sollecita' }))
    await waitFor(() => expect(runActionMock).toHaveBeenCalledWith(10, 'remind'))
    expect(screen.getByRole('button', { name: 'Sollecita' })).toBeEnabled()
  })

  it('shows the sync logs of a request on demand', async () => {
    fetchListMock.mockResolvedValue({ requests: [buildRequest()], canCreate: false })
    fetchLogsMock.mockResolvedValue([
      { id: 1, operation: 'upsert', outcome: 'failed', http_status: 422, attempt: 1, message: 'Checklist non valida', created_at: '2027-03-01T10:00:00Z' },
    ])
    renderSection()

    fireEvent.click(await screen.findByRole('button', { name: 'Sincronizzazioni' }))
    const dialog = await screen.findByRole('dialog')
    expect(await within(dialog).findByText('Checklist non valida')).toBeInTheDocument()
    expect(within(dialog).getByText('Fallita')).toBeInTheDocument()
    expect(fetchLogsMock).toHaveBeenCalledWith(10)
  })

  it('shows an error with retry when the list cannot be loaded', async () => {
    fetchListMock.mockRejectedValueOnce(httpError(500, {}))
    fetchListMock.mockResolvedValue({ requests: [], canCreate: false })
    renderSection()

    expect(await screen.findByRole('alert')).toHaveTextContent('Impossibile caricare le richieste di documenti.')
    fireEvent.click(screen.getByRole('button', { name: 'Riprova' }))
    expect(await screen.findByText('Nessuna richiesta di documenti per questa commessa.')).toBeInTheDocument()
  })
})
