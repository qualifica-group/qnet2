import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OutboundEmailComposerDialog } from '@/features/outbound-emails/outbound-email-composer-dialog'
import type { ComposeContext, ComposeContextDocument, OutboundEmail } from '@/features/outbound-emails/types'

/**
 * The composer's attachments panel (D-7, AC-021): upload, the four import
 * sources (documents / document bundle / quote PDF) and removal — each
 * asserted through the composer dialog, since the section is not usable
 * standalone (it needs the email + compose-context the dialog already
 * fetches). Composer core actions live in `outbound-email-composer.test.tsx`.
 */

const WORK_ORDER_OWNER = { type: 'work-orders', id: 42 } as const

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// `document-bundles` is the only picker this file drives; `email-templates`
// is out of scope here (covered by the composer test).
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: (props: { resource: string; onChange: (id: number | null) => void }) => (
    <button type="button" onClick={() => props.onChange(9)}>
      {`select-stub:${props.resource}`}
    </button>
  ),
}))

const fetchOutboundEmailMock = vi.fn()
const fetchOutboundEmailComposeContextMock = vi.fn()
const uploadOutboundEmailAttachmentMock = vi.fn()
const importOutboundEmailAttachmentsMock = vi.fn()
const removeOutboundEmailAttachmentMock = vi.fn()

vi.mock('@/features/outbound-emails/api', () => ({
  outboundEmailsListQueryKey: (owner: { type: string; id: number }) => ['outbound-emails', owner.type, owner.id, 'list'],
  outboundEmailQueryKey: (owner: { type: string; id: number }, emailId: number) => ['outbound-emails', owner.type, owner.id, 'detail', emailId],
  outboundEmailComposeContextQueryKey: (owner: { type: string; id: number }) => ['outbound-emails', owner.type, owner.id, 'compose-context'],
  fetchOutboundEmail: (...args: unknown[]) => fetchOutboundEmailMock(...args),
  updateOutboundEmail: vi.fn(),
  deleteOutboundEmail: vi.fn(),
  sendOutboundEmail: vi.fn(),
  fetchOutboundEmailComposeContext: (...args: unknown[]) => fetchOutboundEmailComposeContextMock(...args),
  renderOutboundEmailTemplate: vi.fn(),
  uploadOutboundEmailAttachment: (...args: unknown[]) => uploadOutboundEmailAttachmentMock(...args),
  importOutboundEmailAttachments: (...args: unknown[]) => importOutboundEmailAttachmentsMock(...args),
  removeOutboundEmailAttachment: (...args: unknown[]) => removeOutboundEmailAttachmentMock(...args),
  downloadOutboundEmailAttachment: vi.fn(),
}))

function email(overrides: Partial<OutboundEmail> = {}): OutboundEmail {
  return {
    id: 1,
    status: 'draft',
    purpose: null,
    email_template_id: null,
    sender: { id: 9, name: 'Mario Rossi' },
    from_address: null,
    to: [],
    cc: [],
    bcc: [],
    subject: null,
    body: null,
    attachments: [],
    attachments_total_size: 0,
    queued_at: null,
    sent_at: null,
    failed_at: null,
    error_message: null,
    created_at: '2026-09-20T09:00:00.000Z',
    updated_at: '2026-09-20T09:00:00.000Z',
    can: { update: true, delete: true, send: true },
    ...overrides,
  }
}

function composeContext(overrides: Partial<ComposeContext> = {}): ComposeContext {
  return {
    sender: { name: 'Mario Rossi', email: 'mario.rossi@example.com' },
    recipient_suggestions: [],
    documents: [],
    quote_pdf_available: false,
    sources: ['quote_pdf'],
    max_total_attachments_kb: 25600,
    ...overrides,
  }
}

function composeDocument(overrides: Partial<ComposeContextDocument> = {}): ComposeContextDocument {
  return { id: 1, original_name: 'contract.pdf', size: 2048, source: 'work_order', ...overrides }
}

function renderComposer() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    withProviders(
      <OutboundEmailComposerDialog owner={WORK_ORDER_OWNER} emailId={1} justCreated={false} open onOpenChange={vi.fn()} />,
      client,
    ),
  )
}

function withProviders(ui: ReactElement, client: QueryClient) {
  return (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{ui}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchOutboundEmailMock.mockReset()
  fetchOutboundEmailComposeContextMock.mockReset()
  uploadOutboundEmailAttachmentMock.mockReset()
  importOutboundEmailAttachmentsMock.mockReset()
  removeOutboundEmailAttachmentMock.mockReset()
  fetchOutboundEmailComposeContextMock.mockResolvedValue(composeContext())
})

afterEach(() => {
  vi.clearAllMocks()
})

describe('OutboundEmailAttachmentsSection', () => {
  it('uploads a file and shows it in the attachment list from the mutation response', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    uploadOutboundEmailAttachmentMock.mockResolvedValue(
      email({
        attachments: [{ id: 11, original_name: 'brochure.pdf', mime_type: 'application/pdf', size: 4096 }],
        attachments_total_size: 4096,
      }),
    )

    renderComposer()

    await screen.findByLabelText('Subject')
    const file = new File(['%PDF'], 'brochure.pdf', { type: 'application/pdf' })
    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    fireEvent.change(input, { target: { files: [file] } })

    await waitFor(() => expect(uploadOutboundEmailAttachmentMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1, file))
    expect(await screen.findByText('brochure.pdf')).toBeInTheDocument()
  })

  it('imports the quote PDF only when compose-context says it is available', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    fetchOutboundEmailComposeContextMock.mockResolvedValue(composeContext({ quote_pdf_available: true }))
    importOutboundEmailAttachmentsMock.mockResolvedValue(
      email({ attachments: [{ id: 12, original_name: 'quote.pdf', mime_type: 'application/pdf', size: 8192 }] }),
    )

    renderComposer()

    const quotePdfButton = await screen.findByRole('button', { name: 'Quote PDF' })
    expect(quotePdfButton).toBeEnabled()
    fireEvent.click(quotePdfButton)

    await waitFor(() => expect(importOutboundEmailAttachmentsMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1, { source: 'quote_pdf' }))
    expect(await screen.findByText('quote.pdf')).toBeInTheDocument()
  })

  it('disables "Quote PDF" when compose-context says it is unavailable', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    fetchOutboundEmailComposeContextMock.mockResolvedValue(composeContext({ quote_pdf_available: false }))

    renderComposer()

    expect(await screen.findByRole('button', { name: 'Quote PDF' })).toBeDisabled()
  })

  it('imports a document bundle from the picker stub', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    importOutboundEmailAttachmentsMock.mockResolvedValue(
      email({ attachments: [{ id: 13, original_name: 'bundle-file.docx', mime_type: 'application/msword', size: 1024 }] }),
    )

    renderComposer()

    await screen.findByLabelText('Subject')
    fireEvent.click(screen.getByText('select-stub:document-bundles'))

    await waitFor(() =>
      expect(importOutboundEmailAttachmentsMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1, {
        source: 'document_bundle',
        document_bundle_id: 9,
      }),
    )
    expect(await screen.findByText('bundle-file.docx')).toBeInTheDocument()
  })

  it('imports selected commessa/registry documents from the picker dialog', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    fetchOutboundEmailComposeContextMock.mockResolvedValue(
      composeContext({
        documents: [
          composeDocument({ id: 1, original_name: 'work-order-doc.pdf', source: 'work_order' }),
          composeDocument({ id: 2, original_name: 'registry-doc.pdf', source: 'registry' }),
        ],
      }),
    )
    importOutboundEmailAttachmentsMock.mockResolvedValue(
      email({ attachments: [{ id: 14, original_name: 'work-order-doc.pdf', mime_type: 'application/pdf', size: 512 }] }),
    )

    renderComposer()

    fireEvent.click(await screen.findByRole('button', { name: 'From documents' }))
    const dialog = await screen.findByRole('dialog', { name: 'Select documents' })
    expect(within(dialog).getByText('Work order documents')).toBeInTheDocument()
    expect(within(dialog).getByText('Registry documents')).toBeInTheDocument()

    fireEvent.click(within(dialog).getByText('work-order-doc.pdf'))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Add selected' }))

    await waitFor(() =>
      expect(importOutboundEmailAttachmentsMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1, { source: 'documents', attachment_ids: [1] }),
    )
  })

  it('removes an attachment', async () => {
    fetchOutboundEmailMock.mockResolvedValue(
      email({ attachments: [{ id: 20, original_name: 'old.pdf', mime_type: 'application/pdf', size: 100 }] }),
    )
    removeOutboundEmailAttachmentMock.mockResolvedValue(email({ attachments: [] }))

    renderComposer()

    fireEvent.click(await screen.findByRole('button', { name: 'Remove attachment' }))

    await waitFor(() => expect(removeOutboundEmailAttachmentMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1, 20))
    await waitFor(() => expect(screen.queryByText('old.pdf')).not.toBeInTheDocument())
  })
})
