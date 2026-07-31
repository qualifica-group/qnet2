import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { DocumentsSection } from '@/features/attachments/documents-section'
import type { Attachment } from '@/features/attachments/types'

/**
 * Documents section (shared, self-fetching Attachment API consumer): list
 * render (image thumbnail vs. non-image icon), empty/error states, upload,
 * delete-through-confirm, and that thumbnail/preview/download stream the
 * binary through the authenticated client instead of pointing the DOM at the
 * `view_url`/`download_url` endpoints (which answer 401 without the Bearer
 * token the browser never sends on a DOM-issued request).
 */

const DROPZONE_LABEL = 'Drop one or more files here or click to browse'
const OBJECT_URL = 'blob:mock-object-url'

const listAttachmentsMock = vi.fn()
const uploadAttachmentMock = vi.fn()
const deleteAttachmentMock = vi.fn()
const fetchAttachmentBinaryMock = vi.fn()
const saveBlobMock = vi.fn()

vi.mock('@/features/attachments/api', () => ({
  attachmentsQueryKey: (resource: string, id: number, collection: string) =>
    ['attachments', resource, id, collection] as const,
  attachmentBinaryQueryKey: (id: number) => ['attachment-binary', id] as const,
  listAttachments: (...args: unknown[]) => listAttachmentsMock(...args),
  uploadAttachment: (...args: unknown[]) => uploadAttachmentMock(...args),
  deleteAttachment: (...args: unknown[]) => deleteAttachmentMock(...args),
  fetchAttachmentBinary: (...args: unknown[]) => fetchAttachmentBinaryMock(...args),
}))

vi.mock('@/lib/download', () => ({
  saveBlob: (...args: unknown[]) => saveBlobMock(...args),
}))

function attachment(overrides: Partial<Attachment> = {}): Attachment {
  return {
    id: 1,
    collection: 'documents',
    original_name: 'contract.pdf',
    mime_type: 'application/pdf',
    extension: 'pdf',
    size: 2048,
    attachable_type: 'opportunity',
    attachable_id: 42,
    uploaded_by: 9,
    download_url: 'https://api.test/api/attachments/1/download',
    view_url: 'https://api.test/api/attachments/1/view',
    created_at: '2026-07-20T10:00:00.000Z',
    ...overrides,
  }
}

function renderSection(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{ui}</ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // jsdom implements neither: the blob URL is the whole point of these paths.
  URL.createObjectURL = vi.fn(() => OBJECT_URL)
  URL.revokeObjectURL = vi.fn()
})

beforeEach(() => {
  listAttachmentsMock.mockReset()
  uploadAttachmentMock.mockReset()
  deleteAttachmentMock.mockReset()
  saveBlobMock.mockReset()
  fetchAttachmentBinaryMock.mockReset()
  fetchAttachmentBinaryMock.mockResolvedValue(new Blob(['binary']))
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('DocumentsSection', () => {
  it('renders an image thumbnail for image documents and an icon for other files', async () => {
    listAttachmentsMock.mockResolvedValue([
      attachment({ id: 1, original_name: 'photo.png', mime_type: 'image/png' }),
      attachment({ id: 2, original_name: 'contract.pdf', mime_type: 'application/pdf' }),
    ])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    await waitFor(() => expect(screen.getByText('photo.png')).toBeInTheDocument())
    expect(screen.getByText('contract.pdf')).toBeInTheDocument()

    const images = await screen.findAllByRole('img')
    expect(images).toHaveLength(1)
    // The thumbnail comes from the authenticated fetch, never from `view_url`.
    expect(images[0]).toHaveAttribute('src', OBJECT_URL)
    expect(fetchAttachmentBinaryMock).toHaveBeenCalledWith(1, 'view')
    expect(fetchAttachmentBinaryMock).not.toHaveBeenCalledWith(2, 'view')
    expect(listAttachmentsMock).toHaveBeenCalledWith('opportunity', 42, 'documents')
  })

  it('shows the empty state when there are no documents', async () => {
    listAttachmentsMock.mockResolvedValue([])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    await waitFor(() => expect(screen.getByText('No documents yet.')).toBeInTheDocument())
  })

  it('shows the error state with a retry action', async () => {
    listAttachmentsMock.mockRejectedValue(new Error('network error'))

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    await waitFor(() =>
      expect(screen.getByText('Unable to load the documents. Please try again.')).toBeInTheDocument(),
    )
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('previews in a new tab through the authenticated binary fetch', async () => {
    listAttachmentsMock.mockResolvedValue([attachment()])
    const tab = { location: { href: '' }, close: vi.fn() }
    const openSpy = vi.fn(() => tab)
    vi.stubGlobal('open', openSpy)

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    fireEvent.click(await screen.findByRole('button', { name: 'Preview' }))

    // The tab is opened synchronously on the click (after the await the user
    // gesture is gone and the popup blocker swallows it), then navigated.
    expect(openSpy).toHaveBeenCalledWith('', '_blank')
    await waitFor(() => expect(tab.location.href).toBe(OBJECT_URL))
    expect(fetchAttachmentBinaryMock).toHaveBeenCalledWith(1, 'view')
    expect(tab.close).not.toHaveBeenCalled()
  })

  it('closes the opened tab and warns when the preview fetch fails', async () => {
    listAttachmentsMock.mockResolvedValue([attachment()])
    fetchAttachmentBinaryMock.mockRejectedValue(new Error('forbidden'))
    const tab = { location: { href: '' }, close: vi.fn() }
    vi.stubGlobal('open', vi.fn(() => tab))

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    fireEvent.click(await screen.findByRole('button', { name: 'Preview' }))

    await waitFor(() => expect(tab.close).toHaveBeenCalled())
    expect(tab.location.href).toBe('')
  })

  it('downloads through the authenticated binary fetch, keeping the original name', async () => {
    listAttachmentsMock.mockResolvedValue([attachment()])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    fireEvent.click(await screen.findByRole('button', { name: 'Download' }))

    await waitFor(() => expect(fetchAttachmentBinaryMock).toHaveBeenCalledWith(1, 'download'))
    expect(saveBlobMock).toHaveBeenCalledWith(expect.any(Blob), 'contract.pdf')
  })

  it('uploads the dropped/selected file and refreshes the list', async () => {
    listAttachmentsMock.mockResolvedValue([])
    uploadAttachmentMock.mockResolvedValue(attachment())

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload canDelete={false} />,
    )

    await waitFor(() => expect(screen.getByText('No documents yet.')).toBeInTheDocument())

    const file = new File(['%PDF-1.4'], 'contract.pdf', { type: 'application/pdf' })
    fireEvent.change(screen.getByLabelText(DROPZONE_LABEL), {
      target: { files: [file] },
    })

    await waitFor(() =>
      expect(uploadAttachmentMock).toHaveBeenCalledWith({
        resource: 'opportunity',
        id: 42,
        collection: 'documents',
        file,
      }),
    )
    await waitFor(() => expect(listAttachmentsMock).toHaveBeenCalledTimes(2))
  })

  it('accepts a multiple selection and uploads every file once', async () => {
    listAttachmentsMock.mockResolvedValue([])
    uploadAttachmentMock.mockResolvedValue(attachment())

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload canDelete={false} />,
    )

    await waitFor(() => expect(screen.getByText('No documents yet.')).toBeInTheDocument())

    const input = screen.getByLabelText(DROPZONE_LABEL)
    expect(input).toHaveAttribute('multiple')

    const first = new File(['%PDF-1.4'], 'contract.pdf', { type: 'application/pdf' })
    const second = new File(['data'], 'photo.png', { type: 'image/png' })
    fireEvent.change(input, { target: { files: [first, second] } })

    await waitFor(() => expect(uploadAttachmentMock).toHaveBeenCalledTimes(2))
    expect(uploadAttachmentMock).toHaveBeenNthCalledWith(1, expect.objectContaining({ file: first }))
    expect(uploadAttachmentMock).toHaveBeenNthCalledWith(2, expect.objectContaining({ file: second }))
    await waitFor(() => expect(listAttachmentsMock).toHaveBeenCalledTimes(2))
  })

  it('keeps the successful uploads and reports only the failed files of a batch', async () => {
    listAttachmentsMock.mockResolvedValue([])
    uploadAttachmentMock
      .mockResolvedValueOnce(attachment())
      .mockRejectedValueOnce(new Error('too large'))

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload canDelete={false} />,
    )

    await waitFor(() => expect(screen.getByText('No documents yet.')).toBeInTheDocument())

    fireEvent.change(screen.getByLabelText(DROPZONE_LABEL), {
      target: {
        files: [
          new File(['%PDF-1.4'], 'contract.pdf', { type: 'application/pdf' }),
          new File(['data'], 'huge.zip', { type: 'application/zip' }),
        ],
      },
    })

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent(
        'Unable to upload 1 file: huge.zip. The others were uploaded.',
      ),
    )
    expect(listAttachmentsMock).toHaveBeenCalledTimes(2)
  })

  it('does not render the dropzone when the caller has no upload permission', async () => {
    listAttachmentsMock.mockResolvedValue([])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    await waitFor(() => expect(screen.getByText('No documents yet.')).toBeInTheDocument())
    expect(screen.queryByLabelText(DROPZONE_LABEL)).not.toBeInTheDocument()
  })

  it('deletes a document after the confirm dialog is accepted', async () => {
    listAttachmentsMock.mockResolvedValue([attachment()])
    deleteAttachmentMock.mockResolvedValue(undefined)

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete />,
    )

    const deleteButton = await screen.findByRole('button', { name: 'Delete document' })
    fireEvent.click(deleteButton)

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete document' }))

    await waitFor(() => expect(deleteAttachmentMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(listAttachmentsMock).toHaveBeenCalledTimes(2))
  })

  it('does not delete when the confirm dialog is cancelled', async () => {
    listAttachmentsMock.mockResolvedValue([attachment()])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete />,
    )

    const deleteButton = await screen.findByRole('button', { name: 'Delete document' })
    fireEvent.click(deleteButton)

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(deleteAttachmentMock).not.toHaveBeenCalled()
  })

  it('does not render the delete action when the caller has no delete permission', async () => {
    listAttachmentsMock.mockResolvedValue([attachment()])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    await screen.findByText('contract.pdf')
    expect(screen.queryByRole('button', { name: 'Delete document' })).not.toBeInTheDocument()
  })
})
