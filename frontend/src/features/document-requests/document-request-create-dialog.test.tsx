import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@/i18n'
import { buildRequest, httpError } from '@/features/document-requests/document-request-fixtures'
import { DocumentRequestCreateDialog } from '@/features/document-requests/document-request-create-dialog'
import { DocumentRequestEditDialog } from '@/features/document-requests/document-request-edit-dialog'

const createMock = vi.fn()
const updateMock = vi.fn()
const defaultsMock = vi.fn()
vi.mock('@/features/document-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/document-requests/api')>()),
  createDocumentRequest: (id: number, payload: unknown) => createMock(id, payload),
  updateDocumentRequest: (id: number, payload: unknown) => updateMock(id, payload),
  fetchDocumentRequestDefaults: (id: number) => defaultsMock(id),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params) }
})

const PAGINATION = { total: 1, offset: 0, limit: 25, total_pages: 1 }

function renderWithClient(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

function renderCreate() {
  return renderWithClient(<DocumentRequestCreateDialog workOrderId={5} open onOpenChange={() => undefined} />)
}

async function pickOption(trigger: string, option: string) {
  fireEvent.click(await screen.findByRole('combobox', { name: trigger }))
  fireEvent.click(await screen.findByRole('option', { name: new RegExp(option) }))
}

describe('DocumentRequestCreateDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    defaultsMock.mockResolvedValue({ recipient_email: 'cliente@example.com', customer_name: 'ACME Srl' })
    createMock.mockResolvedValue(buildRequest())
    fetchForSelectMock.mockImplementation((resource: string) =>
      Promise.resolve({
        items: [
          resource === 'document-checklists'
            ? { id: 3, label: 'Documenti societari', subtitle: 'ONBOARD' }
            : { id: 8, label: 'Societa' },
        ],
        export_link: null,
        pagination: PAGINATION,
      }),
    )
  })

  it('pre-fills the recipient email from the defaults (AC-018)', async () => {
    renderCreate()

    expect(await screen.findByLabelText(/Email del destinatario/)).toHaveValue('cliente@example.com')
    expect(screen.getByText('Scegli la checklist da inviare a ACME Srl.')).toBeInTheDocument()
  })

  it('still opens with an empty email when the defaults cannot be loaded (AC-018)', async () => {
    defaultsMock.mockRejectedValue(httpError(500, {}))
    renderCreate()

    expect(await screen.findByLabelText(/Email del destinatario/)).toHaveValue('')
  })

  it('narrows the checklist picker by category and posts the chosen checklist (AC-018)', async () => {
    renderCreate()
    await screen.findByLabelText(/Email del destinatario/)

    await pickOption('Categoria', 'Societa')
    await pickOption('Checklist', 'Documenti societari')
    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'document-checklists',
      expect.objectContaining({ params: { category_id: 8 } }),
    )

    fireEvent.change(screen.getByLabelText('Scadenza'), { target: { value: '2999-01-31' } })
    fireEvent.change(screen.getByLabelText('Messaggio'), { target: { value: 'Carica i documenti' } })
    fireEvent.click(screen.getByRole('button', { name: 'Crea richiesta' }))

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith(5, {
        document_checklist_id: 3,
        due_date: '2999-01-31',
        recipient_email: 'cliente@example.com',
        message: 'Carica i documenti',
        send: true,
      }),
    )
  })

  it('requires a checklist and does not call the endpoint (AC-018)', async () => {
    renderCreate()
    await screen.findByLabelText(/Email del destinatario/)

    fireEvent.click(screen.getByRole('button', { name: 'Crea richiesta' }))

    expect(await screen.findByText('Seleziona una checklist.')).toHaveAttribute('id')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('validates past date, invalid email and a message over 2000 characters on the client (AC-018)', async () => {
    renderCreate()
    await pickOption('Checklist', 'Documenti societari')

    fireEvent.change(screen.getByLabelText('Scadenza'), { target: { value: '2000-01-01' } })
    fireEvent.change(screen.getByLabelText(/Email del destinatario/), { target: { value: 'non-una-email' } })
    fireEvent.change(screen.getByLabelText('Messaggio'), { target: { value: 'x'.repeat(2001) } })
    fireEvent.click(screen.getByRole('button', { name: 'Crea richiesta' }))

    expect(await screen.findByText('La scadenza non può essere nel passato.')).toBeInTheDocument()
    expect(screen.getByText('Inserisci un indirizzo email valido.')).toBeInTheDocument()
    expect(screen.getByLabelText('Scadenza')).toHaveAttribute('aria-invalid', 'true')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('requires the recipient email when the customer has none (AC-021)', async () => {
    defaultsMock.mockResolvedValue({ recipient_email: null, customer_name: 'ACME Srl' })
    renderCreate()
    await pickOption('Checklist', 'Documenti societari')

    fireEvent.click(screen.getByRole('button', { name: 'Crea richiesta' }))

    expect(await screen.findByText("Indica l'email del destinatario.")).toBeInTheDocument()
    expect(screen.getByLabelText(/Email del destinatario/)).toHaveAttribute('aria-invalid', 'true')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('shows a server 422 on the field it refers to (AC-018)', async () => {
    createMock.mockRejectedValue(httpError(422, { message: 'Errore', errors: { recipient_email: ['Indirizzo rifiutato dal server'] } }))
    renderCreate()
    await pickOption('Checklist', 'Documenti societari')

    fireEvent.click(screen.getByRole('button', { name: 'Crea richiesta' }))

    await waitFor(() => expect(screen.getByLabelText(/Email del destinatario/)).toHaveAttribute('aria-invalid', 'true'))
    expect(screen.getByLabelText(/Email del destinatario/)).toHaveAccessibleDescription(/Indirizzo rifiutato dal server/)
  })
})

describe('DocumentRequestEditDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    updateMock.mockResolvedValue(buildRequest())
  })

  it('sends only due date, recipient, message and send (AC-018)', async () => {
    renderWithClient(
      <DocumentRequestEditDialog
        request={buildRequest({ due_date: '2999-05-01', message: 'Ciao' })}
        open
        onOpenChange={() => undefined}
      />,
    )

    const email = screen.getByLabelText(/Email del destinatario/)
    expect(email).toHaveValue('cliente@example.com')
    fireEvent.change(email, { target: { value: 'altro@example.com' } })
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Salva' }))

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(10, {
        due_date: '2999-05-01',
        recipient_email: 'altro@example.com',
        message: 'Ciao',
        send: true,
      }),
    )
  })
})
