import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { AxiosError, AxiosHeaders } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { WorkOrderEmailComposerDialog } from '@/features/work-order-emails/work-order-email-composer-dialog'
import type { ComposeContext, OutboundEmail } from '@/features/work-order-emails/types'

/**
 * The composer's own core actions (AC-020): save draft, send (save-then-send
 * and its client-side validation), a 422 mapped onto a field, and the
 * template picker's overwrite confirm. Attachments live in
 * `work-order-email-attachments-section.test.tsx`, the read-only detail in
 * `work-order-email-detail-dialog.test.tsx`, navigation/close-gates in
 * `work-order-emails-panel.test.tsx`.
 */

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

const fetchWorkOrderEmailMock = vi.fn()
const updateWorkOrderEmailMock = vi.fn()
const sendWorkOrderEmailMock = vi.fn()
const fetchWorkOrderEmailComposeContextMock = vi.fn()
const renderWorkOrderEmailTemplateMock = vi.fn()

vi.mock('@/features/work-order-emails/api', () => ({
  workOrderEmailsListQueryKey: (workOrderId: number) => ['work-order-emails', workOrderId, 'list'],
  workOrderEmailQueryKey: (workOrderId: number, emailId: number) => ['work-order-emails', workOrderId, 'detail', emailId],
  workOrderEmailComposeContextQueryKey: (workOrderId: number) => ['work-order-emails', workOrderId, 'compose-context'],
  fetchWorkOrderEmail: (...args: unknown[]) => fetchWorkOrderEmailMock(...args),
  updateWorkOrderEmail: (...args: unknown[]) => updateWorkOrderEmailMock(...args),
  deleteWorkOrderEmail: vi.fn(),
  sendWorkOrderEmail: (...args: unknown[]) => sendWorkOrderEmailMock(...args),
  fetchWorkOrderEmailComposeContext: (...args: unknown[]) => fetchWorkOrderEmailComposeContextMock(...args),
  renderWorkOrderEmailTemplate: (...args: unknown[]) => renderWorkOrderEmailTemplateMock(...args),
  uploadWorkOrderEmailAttachment: vi.fn(),
  importWorkOrderEmailAttachments: vi.fn(),
  removeWorkOrderEmailAttachment: vi.fn(),
  downloadWorkOrderEmailAttachment: vi.fn(),
}))

function email(overrides: Partial<OutboundEmail> = {}): OutboundEmail {
  return {
    id: 1,
    status: 'draft',
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

function renderComposer() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onOpenChange = vi.fn()
  render(
    withProviders(
      <WorkOrderEmailComposerDialog workOrderId={42} emailId={1} justCreated={false} open onOpenChange={onOpenChange} />,
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
  fetchWorkOrderEmailMock.mockReset()
  updateWorkOrderEmailMock.mockReset()
  sendWorkOrderEmailMock.mockReset()
  fetchWorkOrderEmailComposeContextMock.mockReset()
  renderWorkOrderEmailTemplateMock.mockReset()
  fetchWorkOrderEmailComposeContextMock.mockResolvedValue(composeContext())
})

afterEach(() => {
  vi.clearAllMocks()
})

describe('WorkOrderEmailComposerDialog', () => {
  it('saves the draft via PATCH on "Save draft"', async () => {
    fetchWorkOrderEmailMock.mockResolvedValue(email())
    updateWorkOrderEmailMock.mockResolvedValue(email({ subject: 'Hello' }))

    renderComposer()

    const subject = await screen.findByLabelText('Subject')
    fireEvent.change(subject, { target: { value: 'Hello' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save draft' }))

    await waitFor(() =>
      expect(updateWorkOrderEmailMock).toHaveBeenCalledWith(42, 1, expect.objectContaining({ subject: 'Hello' })),
    )
  })

  it('blocks "Send" on an empty draft with client-side validation, without calling the API', async () => {
    fetchWorkOrderEmailMock.mockResolvedValue(email())

    renderComposer()

    await screen.findByLabelText('Subject')
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    expect(await screen.findByText('Add at least one recipient.')).toBeInTheDocument()
    expect(screen.getByText('Subject is required.')).toBeInTheDocument()
    expect(screen.getByText('Body is required.')).toBeInTheDocument()
    expect(updateWorkOrderEmailMock).not.toHaveBeenCalled()
    expect(sendWorkOrderEmailMock).not.toHaveBeenCalled()
  })

  it('"Send" saves then sends and closes the dialog', async () => {
    fetchWorkOrderEmailMock.mockResolvedValue(email({ to: ['client@example.com'], subject: 'Hello', body: '<p>Hi</p>' }))
    updateWorkOrderEmailMock.mockResolvedValue(email({ to: ['client@example.com'], subject: 'Hello', body: '<p>Hi</p>' }))
    sendWorkOrderEmailMock.mockResolvedValue(
      email({ status: 'queued', to: ['client@example.com'], subject: 'Hello', body: '<p>Hi</p>' }),
    )

    const { onOpenChange } = renderComposer()

    await screen.findByDisplayValue('Hello')
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => expect(updateWorkOrderEmailMock).toHaveBeenCalledWith(42, 1, expect.any(Object)))
    await waitFor(() => expect(sendWorkOrderEmailMock).toHaveBeenCalledWith(42, 1))
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
  })

  it('maps a 422 from the save onto the subject field', async () => {
    fetchWorkOrderEmailMock.mockResolvedValue(email())
    updateWorkOrderEmailMock.mockRejectedValue(conflict422({ subject: ['Subject already used.'] }))

    renderComposer()

    const subject = await screen.findByLabelText('Subject')
    fireEvent.change(subject, { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save draft' }))

    expect(await screen.findByText('Subject already used.')).toBeInTheDocument()
  })

  it('picking a template on an empty draft renders and fills subject/body without asking to confirm', async () => {
    fetchWorkOrderEmailMock.mockResolvedValue(email())
    renderWorkOrderEmailTemplateMock.mockResolvedValue({ subject: 'Rendered subject', body: '<p>Rendered body</p>' })

    renderComposer()

    await screen.findByLabelText('Subject')
    fireEvent.click(screen.getByText('select-stub:email-templates'))

    await waitFor(() => expect(renderWorkOrderEmailTemplateMock).toHaveBeenCalledWith(42, 5))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    await waitFor(() => expect(screen.getByDisplayValue('Rendered subject')).toBeInTheDocument())
  })

  it('picking a template with existing text asks to confirm the overwrite first', async () => {
    fetchWorkOrderEmailMock.mockResolvedValue(email({ subject: 'Already typed' }))
    renderWorkOrderEmailTemplateMock.mockResolvedValue({ subject: 'Rendered subject', body: '<p>Rendered body</p>' })

    renderComposer()

    await screen.findByDisplayValue('Already typed')
    fireEvent.click(screen.getByText('select-stub:email-templates'))

    const confirmDialog = await screen.findByRole('alertdialog')
    expect(within(confirmDialog).getByText('Overwrite subject and body?')).toBeInTheDocument()
    expect(renderWorkOrderEmailTemplateMock).not.toHaveBeenCalled()

    fireEvent.click(within(confirmDialog).getByRole('button', { name: 'Overwrite' }))

    await waitFor(() => expect(renderWorkOrderEmailTemplateMock).toHaveBeenCalledWith(42, 5))
    await waitFor(() => expect(screen.getByDisplayValue('Rendered subject')).toBeInTheDocument())
  })
})
