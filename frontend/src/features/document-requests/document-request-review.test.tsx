import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { buildItem, buildRequest, httpError } from '@/features/document-requests/document-request-fixtures'
import { DocumentRequestsSection } from '@/features/document-requests/document-requests-section'
import type { DocumentRequest, DocumentRequestList, ReviewDocumentRequestItemPayload } from '@/features/document-requests/types'

const fetchListMock = vi.fn<(workOrderId: number) => Promise<DocumentRequestList>>()
const runActionMock = vi.fn<(id: number, action: string) => Promise<void>>()
const reviewMock = vi.fn<(id: number, payload: ReviewDocumentRequestItemPayload) => Promise<void>>()
const downloadMock = vi.fn<(id: number, documentId: number | string) => Promise<Blob>>()
const saveBlobMock = vi.fn()
const toastError = vi.fn()

vi.mock('@/features/document-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/document-requests/api')>()),
  fetchDocumentRequests: (id: number) => fetchListMock(id),
  runDocumentRequestAction: (id: number, action: string) => runActionMock(id, action),
  reviewDocumentRequestItem: (id: number, payload: ReviewDocumentRequestItemPayload) => reviewMock(id, payload),
  downloadDocumentRequestFile: (id: number, documentId: number | string) => downloadMock(id, documentId),
}))
vi.mock('@/lib/download', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/download')>()),
  saveBlob: (blob: Blob, name: string) => saveBlobMock(blob, name),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: (message: string) => toastError(message) } }))

const DOCUMENT = {
  id: 77,
  slot: 1,
  version: 1,
  is_current: true,
  status: 'ok',
  status_reason: null,
  original_name: 'visura.pdf',
  mime_type: 'application/pdf',
  size_bytes: 2048,
  created_at: null,
}

function requestWith(status: string, permissions: Partial<DocumentRequest['permissions']> = {}, withDocument = true) {
  return buildRequest({
    items: [
      buildItem({
        status,
        documents: withDocument ? [DOCUMENT] : [],
        current_document_id: withDocument ? 77 : null,
      }),
    ],
    permissions: { update: true, remind: true, cancel: true, review: true, complete: true, refresh_rules: false, ...permissions },
  })
}

async function renderSection(request: DocumentRequest) {
  fetchListMock.mockResolvedValue({ requests: [request], canCreate: false })
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const Wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
  render(<DocumentRequestsSection workOrderId={5} />, { wrapper: Wrapper })
  await screen.findByRole('article')
}

describe('document request review, download and complete (spec 0429)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    runActionMock.mockResolvedValue(undefined)
    reviewMock.mockResolvedValue(undefined)
  })

  it('downloads a file with its name and shows a toast on error (AC-010)', async () => {
    const blob = new Blob(['x'])
    downloadMock.mockResolvedValueOnce(blob).mockRejectedValueOnce(httpError(502, { message: 'x' }))
    await renderSection(requestWith('ok', { review: false }))

    fireEvent.click(screen.getByRole('button', { name: 'Scarica visura.pdf' }))
    await waitFor(() => expect(saveBlobMock).toHaveBeenCalledWith(blob, 'visura.pdf'))
    expect(downloadMock).toHaveBeenCalledWith(10, 77)

    fireEvent.click(screen.getByRole('button', { name: 'Scarica visura.pdf' }))
    await waitFor(() => expect(toastError).toHaveBeenCalledWith('Impossibile scaricare il file.'))
  })

  it('offers approve and reject on a reviewable item (AC-011)', async () => {
    await renderSection(requestWith('review'))
    expect(screen.getByRole('button', { name: 'Approva' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Rifiuta' })).toBeInTheDocument()
  })

  it.each([
    ['ok', 'Approva', 'Rifiuta'],
    ['approved_manually', 'Approva', 'Rifiuta'],
    ['ko', 'Rifiuta', 'Approva'],
    ['rejected_manually', 'Rifiuta', 'Approva'],
  ])('with status %s hides %s and shows %s (AC-011)', async (status, hidden, shown) => {
    await renderSection(requestWith(status))
    expect(screen.queryByRole('button', { name: hidden })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: shown })).toBeInTheDocument()
  })

  it('hides review without the permission (AC-011)', async () => {
    await renderSection(requestWith('review', { review: false }))
    expect(screen.queryByRole('button', { name: 'Approva' })).not.toBeInTheDocument()
  })

  it('hides review on an item without a document (AC-011)', async () => {
    await renderSection(requestWith('pending', {}, false))
    expect(screen.queryByRole('button', { name: 'Approva' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Rifiuta' })).not.toBeInTheDocument()
  })

  it('approves with an optional note: an empty note is sent as null (AC-011)', async () => {
    await renderSection(requestWith('review'))
    fireEvent.click(screen.getByRole('button', { name: 'Approva' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Approva' }))
    await waitFor(() => expect(reviewMock).toHaveBeenCalledWith(10, { itemId: 1, decision: 'approve', note: null }))
  })

  it('requires the note to reject, with the accessible error on the field (AC-011)', async () => {
    await renderSection(requestWith('review'))
    fireEvent.click(screen.getByRole('button', { name: 'Rifiuta' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Rifiuta' }))

    const field = await within(dialog).findByRole('textbox', { name: /Motivo del rifiuto/ })
    await waitFor(() => expect(field).toHaveAttribute('aria-invalid', 'true'))
    expect(within(dialog).getByRole('alert')).toHaveTextContent('Indica il motivo del rifiuto.')
    expect(reviewMock).not.toHaveBeenCalled()

    fireEvent.change(field, { target: { value: 'Illeggibile' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Rifiuta' }))
    await waitFor(() =>
      expect(reviewMock).toHaveBeenCalledWith(10, { itemId: 1, decision: 'reject', note: 'Illeggibile' }),
    )
  })

  it('shows a 422 on the note field (AC-011)', async () => {
    reviewMock.mockRejectedValue(httpError(422, { message: 'invalid', errors: { note: ['Nota non valida dal server'] } }))
    await renderSection(requestWith('review'))
    fireEvent.click(screen.getByRole('button', { name: 'Approva' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Approva' }))
    expect(await within(dialog).findByText('Nota non valida dal server')).toBeInTheDocument()
  })

  it('closes the request after a confirmation that explains the difference from cancel (AC-012)', async () => {
    await renderSection(requestWith('ok'))
    fireEvent.click(screen.getByRole('button', { name: 'Chiudi richiesta' }))
    const dialog = await screen.findByRole('alertdialog')
    expect(dialog).toHaveTextContent(/A differenza di «Annulla richiesta»/)
    expect(runActionMock).not.toHaveBeenCalled()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Chiudi richiesta' }))
    await waitFor(() => expect(runActionMock).toHaveBeenCalledWith(10, 'complete'))
  })

  it('hides Chiudi richiesta without permissions.complete (AC-012)', async () => {
    await renderSection(requestWith('ok', { complete: false }))
    expect(screen.queryByRole('button', { name: 'Chiudi richiesta' })).not.toBeInTheDocument()
  })

  it.each(['pending', 'uploaded', 'validating', 'ok', 'ko', 'review', 'approved_manually', 'rejected_manually'])(
    'labels the item status %s in IT and EN (AC-013)',
    (status) => {
      for (const lng of ['it', 'en']) {
        expect(i18n.t(`documentRequests.itemStatus.${status}`, { lng })).not.toBe(`documentRequests.itemStatus.${status}`)
      }
    },
  )
})
