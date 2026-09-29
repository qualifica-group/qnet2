import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { WorkOrderEmailsPanel } from '@/features/work-order-emails/work-order-emails-panel'
import type { ComposeContext, OutboundEmail, OutboundEmailListItem } from '@/features/work-order-emails/types'

/**
 * The panel's own navigation (AC-019/020): list rendering with status
 * badges, "Nuova email" opening a fresh composer, a draft row reopening the
 * composer, a non-draft row opening the read-only detail, and the two close
 * gates (silent cleanup of an untouched fresh draft vs. confirm on unsaved
 * changes, spec D-2). Save/send/validation/template/attachments live in
 * `work-order-email-composer.test.tsx`.
 */

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: (props: { resource: string }) => <div>{`select-stub:${props.resource}`}</div>,
}))

const listWorkOrderEmailsMock = vi.fn()
const createWorkOrderEmailDraftMock = vi.fn()
const fetchWorkOrderEmailMock = vi.fn()
const updateWorkOrderEmailMock = vi.fn()
const deleteWorkOrderEmailMock = vi.fn()
const sendWorkOrderEmailMock = vi.fn()
const fetchWorkOrderEmailComposeContextMock = vi.fn()
const renderWorkOrderEmailTemplateMock = vi.fn()
const uploadWorkOrderEmailAttachmentMock = vi.fn()
const importWorkOrderEmailAttachmentsMock = vi.fn()
const removeWorkOrderEmailAttachmentMock = vi.fn()
const downloadWorkOrderEmailAttachmentMock = vi.fn()

vi.mock('@/features/work-order-emails/api', () => ({
  workOrderEmailsListQueryKey: (workOrderId: number) => ['work-order-emails', workOrderId, 'list'],
  workOrderEmailQueryKey: (workOrderId: number, emailId: number) => ['work-order-emails', workOrderId, 'detail', emailId],
  workOrderEmailComposeContextQueryKey: (workOrderId: number) => ['work-order-emails', workOrderId, 'compose-context'],
  listWorkOrderEmails: (...args: unknown[]) => listWorkOrderEmailsMock(...args),
  createWorkOrderEmailDraft: (...args: unknown[]) => createWorkOrderEmailDraftMock(...args),
  fetchWorkOrderEmail: (...args: unknown[]) => fetchWorkOrderEmailMock(...args),
  updateWorkOrderEmail: (...args: unknown[]) => updateWorkOrderEmailMock(...args),
  deleteWorkOrderEmail: (...args: unknown[]) => deleteWorkOrderEmailMock(...args),
  sendWorkOrderEmail: (...args: unknown[]) => sendWorkOrderEmailMock(...args),
  fetchWorkOrderEmailComposeContext: (...args: unknown[]) => fetchWorkOrderEmailComposeContextMock(...args),
  renderWorkOrderEmailTemplate: (...args: unknown[]) => renderWorkOrderEmailTemplateMock(...args),
  uploadWorkOrderEmailAttachment: (...args: unknown[]) => uploadWorkOrderEmailAttachmentMock(...args),
  importWorkOrderEmailAttachments: (...args: unknown[]) => importWorkOrderEmailAttachmentsMock(...args),
  removeWorkOrderEmailAttachment: (...args: unknown[]) => removeWorkOrderEmailAttachmentMock(...args),
  downloadWorkOrderEmailAttachment: (...args: unknown[]) => downloadWorkOrderEmailAttachmentMock(...args),
}))

function listItem(overrides: Partial<OutboundEmailListItem> = {}): OutboundEmailListItem {
  return {
    id: 1,
    status: 'sent',
    sender: { id: 9, name: 'Mario Rossi' },
    to: ['client@example.com'],
    subject: 'Hello',
    attachments_count: 0,
    sent_at: '2026-09-20T10:00:00.000Z',
    failed_at: null,
    updated_at: '2026-09-20T10:00:00.000Z',
    ...overrides,
  }
}

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

function renderPanel(props: { canSend?: boolean } = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(withProviders(<WorkOrderEmailsPanel workOrderId={42} canSend={props.canSend ?? true} />, client))
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
  listWorkOrderEmailsMock.mockReset()
  createWorkOrderEmailDraftMock.mockReset()
  fetchWorkOrderEmailMock.mockReset()
  updateWorkOrderEmailMock.mockReset()
  deleteWorkOrderEmailMock.mockReset()
  sendWorkOrderEmailMock.mockReset()
  fetchWorkOrderEmailComposeContextMock.mockReset()
  fetchWorkOrderEmailComposeContextMock.mockResolvedValue(composeContext())
})

afterEach(() => {
  vi.clearAllMocks()
})

describe('WorkOrderEmailsPanel', () => {
  it('lists the history with status badges and opens the read-only detail on a non-draft row', async () => {
    listWorkOrderEmailsMock.mockResolvedValue({
      data: [listItem({ id: 1, status: 'sent', subject: 'Quote follow-up' })],
      meta: { current_page: 1, last_page: 1, total: 1 },
    })
    fetchWorkOrderEmailMock.mockResolvedValue(email({ id: 1, status: 'sent', subject: 'Quote follow-up', to: ['client@example.com'], body: '<p>Hi</p>' }))

    renderPanel()

    await screen.findByText('Quote follow-up')
    expect(screen.getByText('Sent')).toBeInTheDocument()

    fireEvent.click(screen.getByText('Quote follow-up'))

    const dialog = await screen.findByRole('dialog', { name: 'Email details' })
    await waitFor(() => expect(within(dialog).getByText('client@example.com')).toBeInTheDocument())
    expect(fetchWorkOrderEmailMock).toHaveBeenCalledWith(42, 1)
  })

  it('reopens the composer on a draft row, hydrating it from the loaded draft', async () => {
    listWorkOrderEmailsMock.mockResolvedValue({
      data: [listItem({ id: 5, status: 'draft', subject: 'Draft subject', sent_at: null })],
      meta: { current_page: 1, last_page: 1, total: 1 },
    })
    fetchWorkOrderEmailMock.mockResolvedValue(email({ id: 5, status: 'draft', subject: 'Draft subject' }))

    renderPanel()

    fireEvent.click(await screen.findByText('Draft subject'))

    const dialog = await screen.findByRole('dialog', { name: 'Edit draft' })
    await waitFor(() => expect(within(dialog).getByDisplayValue('Draft subject')).toBeInTheDocument())
  })

  it('"New email" creates an empty draft and opens the composer on it', async () => {
    listWorkOrderEmailsMock.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
    createWorkOrderEmailDraftMock.mockResolvedValue(email({ id: 200 }))
    fetchWorkOrderEmailMock.mockResolvedValue(email({ id: 200 }))

    renderPanel()

    await screen.findByText('No emails for this work order yet.')
    fireEvent.click(screen.getByRole('button', { name: 'New email' }))

    await screen.findByRole('dialog', { name: 'New email' })
    expect(createWorkOrderEmailDraftMock).toHaveBeenCalledWith(42)
    await waitFor(() => expect(fetchWorkOrderEmailMock).toHaveBeenCalledWith(42, 200))
  })

  it('does not render "New email" without send permission', async () => {
    listWorkOrderEmailsMock.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })

    renderPanel({ canSend: false })

    await screen.findByText('No emails for this work order yet.')
    expect(screen.queryByRole('button', { name: 'New email' })).not.toBeInTheDocument()
  })

  it('silently deletes an untouched fresh draft on close, without asking to confirm', async () => {
    listWorkOrderEmailsMock.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
    createWorkOrderEmailDraftMock.mockResolvedValue(email({ id: 200 }))
    fetchWorkOrderEmailMock.mockResolvedValue(email({ id: 200 }))
    deleteWorkOrderEmailMock.mockResolvedValue(undefined)

    renderPanel()

    await screen.findByText('No emails for this work order yet.')
    fireEvent.click(screen.getByRole('button', { name: 'New email' }))
    const dialog = await screen.findByRole('dialog', { name: 'New email' })
    await waitFor(() => expect(fetchWorkOrderEmailMock).toHaveBeenCalledWith(42, 200))

    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(deleteWorkOrderEmailMock).toHaveBeenCalledWith(42, 200))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'New email' })).not.toBeInTheDocument())
  })

  it('asks for confirmation when closing a draft with unsaved changes, and keeps it open on cancel', async () => {
    listWorkOrderEmailsMock.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
    createWorkOrderEmailDraftMock.mockResolvedValue(email({ id: 200 }))
    fetchWorkOrderEmailMock.mockResolvedValue(email({ id: 200 }))

    renderPanel()

    await screen.findByText('No emails for this work order yet.')
    fireEvent.click(screen.getByRole('button', { name: 'New email' }))
    const dialog = await screen.findByRole('dialog', { name: 'New email' })
    await waitFor(() => expect(fetchWorkOrderEmailMock).toHaveBeenCalledWith(42, 200))

    fireEvent.change(within(dialog).getByLabelText('Subject'), { target: { value: 'Typed subject' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    const confirmDialog = await screen.findByRole('alertdialog')
    expect(within(confirmDialog).getByText('Unsaved changes')).toBeInTheDocument()

    fireEvent.click(within(confirmDialog).getByRole('button', { name: 'Cancel' }))
    expect(deleteWorkOrderEmailMock).not.toHaveBeenCalled()
    expect(screen.getByRole('dialog', { name: 'New email' })).toBeInTheDocument()

    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))
    const confirmDialogAgain = await screen.findByRole('alertdialog')
    fireEvent.click(within(confirmDialogAgain).getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'New email' })).not.toBeInTheDocument())
    expect(deleteWorkOrderEmailMock).not.toHaveBeenCalled()
    expect(updateWorkOrderEmailMock).not.toHaveBeenCalled()
  })
})
