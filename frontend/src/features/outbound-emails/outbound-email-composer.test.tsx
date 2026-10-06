import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { AxiosError, AxiosHeaders } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OutboundEmailComposerDialog } from '@/features/outbound-emails/outbound-email-composer-dialog'
import type { ComposeContext, OutboundEmail } from '@/features/outbound-emails/types'

/**
 * The composer's own core actions (AC-020): save draft, send (save-then-send
 * and its client-side validation), a 422 mapped onto a field, and the
 * template picker's overwrite confirm. Attachments live in
 * `outbound-email-attachments-section.test.tsx`, the read-only detail in
 * `outbound-email-detail-dialog.test.tsx`, navigation/close-gates in
 * `outbound-emails-panel.test.tsx`.
 */

const WORK_ORDER_OWNER = { type: 'work-orders', id: 42 } as const

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// The template picker is the shared `AsyncPaginatedSelect` (not owned by this
// feature, already covered by its own suite): stubbed as a button that fires
// a fixed id, so this file only exercises the wiring.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: (props: { resource: string; onChange: (id: number | null) => void }) => (
    <button type="button" onClick={() => props.onChange(5)}>
      {`select-stub:${props.resource}`}
    </button>
  ),
}))

const fetchOutboundEmailMock = vi.fn()
const updateOutboundEmailMock = vi.fn()
const sendOutboundEmailMock = vi.fn()
const fetchOutboundEmailComposeContextMock = vi.fn()
const renderOutboundEmailTemplateMock = vi.fn()

vi.mock('@/features/outbound-emails/api', () => ({
  outboundEmailsListQueryKey: (owner: { type: string; id: number }) => ['outbound-emails', owner.type, owner.id, 'list'],
  outboundEmailQueryKey: (owner: { type: string; id: number }, emailId: number) => ['outbound-emails', owner.type, owner.id, 'detail', emailId],
  outboundEmailComposeContextQueryKey: (owner: { type: string; id: number }) => ['outbound-emails', owner.type, owner.id, 'compose-context'],
  fetchOutboundEmail: (...args: unknown[]) => fetchOutboundEmailMock(...args),
  updateOutboundEmail: (...args: unknown[]) => updateOutboundEmailMock(...args),
  deleteOutboundEmail: vi.fn(),
  sendOutboundEmail: (...args: unknown[]) => sendOutboundEmailMock(...args),
  fetchOutboundEmailComposeContext: (...args: unknown[]) => fetchOutboundEmailComposeContextMock(...args),
  renderOutboundEmailTemplate: (...args: unknown[]) => renderOutboundEmailTemplateMock(...args),
  uploadOutboundEmailAttachment: vi.fn(),
  importOutboundEmailAttachments: vi.fn(),
  removeOutboundEmailAttachment: vi.fn(),
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

function conflict422(errors: Record<string, string[]>): AxiosError {
  return new AxiosError('Validation failed', '422', undefined, undefined, {
    status: 422,
    statusText: 'Unprocessable Content',
    headers: new AxiosHeaders(),
    config: { headers: new AxiosHeaders() },
    data: { success: false, message: 'Validation failed', errors },
  })
}

function renderComposer(props: { justCreated?: boolean } = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onOpenChange = vi.fn()
  render(
    withProviders(
      <OutboundEmailComposerDialog owner={WORK_ORDER_OWNER} emailId={1} justCreated={props.justCreated ?? false} open onOpenChange={onOpenChange} />,
      client,
    ),
  )
  return { onOpenChange }
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
  updateOutboundEmailMock.mockReset()
  sendOutboundEmailMock.mockReset()
  fetchOutboundEmailComposeContextMock.mockReset()
  renderOutboundEmailTemplateMock.mockReset()
  fetchOutboundEmailComposeContextMock.mockResolvedValue(composeContext())
})

afterEach(() => {
  vi.clearAllMocks()
})

describe('OutboundEmailComposerDialog', () => {
  it('prefills To from compose-context default_to on a freshly created draft', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email({ to: [] }))
    fetchOutboundEmailComposeContextMock.mockResolvedValue(composeContext({ default_to: ['billing@acme.test'] }))

    renderComposer({ justCreated: true })

    expect(await screen.findByText('billing@acme.test')).toBeInTheDocument()
  })

  it('saves the draft via PATCH on "Save draft"', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    updateOutboundEmailMock.mockResolvedValue(email({ subject: 'Hello' }))

    renderComposer()

    const subject = await screen.findByLabelText('Subject')
    fireEvent.change(subject, { target: { value: 'Hello' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save draft' }))

    await waitFor(() =>
      expect(updateOutboundEmailMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1, expect.objectContaining({ subject: 'Hello' })),
    )
  })

  it('blocks "Send" on an empty draft with client-side validation, without calling the API', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())

    renderComposer()

    await screen.findByLabelText('Subject')
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    expect(await screen.findByText('Add at least one recipient.')).toBeInTheDocument()
    expect(screen.getByText('Subject is required.')).toBeInTheDocument()
    expect(screen.getByText('Body is required.')).toBeInTheDocument()
    expect(updateOutboundEmailMock).not.toHaveBeenCalled()
    expect(sendOutboundEmailMock).not.toHaveBeenCalled()
  })

  it('"Send" saves then sends and closes the dialog', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email({ to: ['client@example.com'], subject: 'Hello', body: '<p>Hi</p>' }))
    updateOutboundEmailMock.mockResolvedValue(email({ to: ['client@example.com'], subject: 'Hello', body: '<p>Hi</p>' }))
    sendOutboundEmailMock.mockResolvedValue(
      email({ status: 'queued', to: ['client@example.com'], subject: 'Hello', body: '<p>Hi</p>' }),
    )

    const { onOpenChange } = renderComposer()

    await screen.findByDisplayValue('Hello')
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => expect(updateOutboundEmailMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1, expect.any(Object)))
    await waitFor(() => expect(sendOutboundEmailMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1))
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
  })

  it('maps a 422 from the save onto the subject field', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    updateOutboundEmailMock.mockRejectedValue(conflict422({ subject: ['Subject already used.'] }))

    renderComposer()

    const subject = await screen.findByLabelText('Subject')
    fireEvent.change(subject, { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save draft' }))

    expect(await screen.findByText('Subject already used.')).toBeInTheDocument()
  })

  it('picking a template on an empty draft renders and fills subject/body without asking to confirm', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    renderOutboundEmailTemplateMock.mockResolvedValue({ subject: 'Rendered subject', body: '<p>Rendered body</p>' })

    renderComposer()

    await screen.findByLabelText('Subject')
    fireEvent.click(screen.getByText('select-stub:email-templates'))

    await waitFor(() => expect(renderOutboundEmailTemplateMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 5))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    await waitFor(() => expect(screen.getByDisplayValue('Rendered subject')).toBeInTheDocument())
  })

  it('picking a template with existing text asks to confirm the overwrite first', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email({ subject: 'Already typed' }))
    renderOutboundEmailTemplateMock.mockResolvedValue({ subject: 'Rendered subject', body: '<p>Rendered body</p>' })

    renderComposer()

    await screen.findByDisplayValue('Already typed')
    fireEvent.click(screen.getByText('select-stub:email-templates'))

    const confirmDialog = await screen.findByRole('alertdialog')
    expect(within(confirmDialog).getByText('Overwrite subject and body?')).toBeInTheDocument()
    expect(renderOutboundEmailTemplateMock).not.toHaveBeenCalled()

    fireEvent.click(within(confirmDialog).getByRole('button', { name: 'Overwrite' }))

    await waitFor(() => expect(renderOutboundEmailTemplateMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 5))
    await waitFor(() => expect(screen.getByDisplayValue('Rendered subject')).toBeInTheDocument())
  })
})
