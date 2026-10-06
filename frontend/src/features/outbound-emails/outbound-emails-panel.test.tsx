import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OutboundEmailsPanel } from '@/features/outbound-emails/outbound-emails-panel'
import type { ComposeContext, OutboundEmail, OutboundEmailListItem } from '@/features/outbound-emails/types'

/**
 * The panel's own navigation (AC-019/020): list rendering with status
 * badges, "Nuova email" opening a fresh composer, a draft row reopening the
 * composer, a non-draft row opening the read-only detail, and the two close
 * gates (silent cleanup of an untouched fresh draft vs. confirm on unsaved
 * changes, spec D-2). Save/send/validation/template/attachments live in
 * `outbound-email-composer.test.tsx`.
 */

const WORK_ORDER_OWNER = { type: 'work-orders', id: 42 } as const

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: (props: { resource: string }) => <div>{`select-stub:${props.resource}`}</div>,
}))

const listOutboundEmailsMock = vi.fn()
const createOutboundEmailDraftMock = vi.fn()
const fetchOutboundEmailMock = vi.fn()
const updateOutboundEmailMock = vi.fn()
const deleteOutboundEmailMock = vi.fn()
const sendOutboundEmailMock = vi.fn()
const fetchOutboundEmailComposeContextMock = vi.fn()
const renderOutboundEmailTemplateMock = vi.fn()
const uploadOutboundEmailAttachmentMock = vi.fn()
const importOutboundEmailAttachmentsMock = vi.fn()
const removeOutboundEmailAttachmentMock = vi.fn()
const downloadOutboundEmailAttachmentMock = vi.fn()

vi.mock('@/features/outbound-emails/api', () => ({
  outboundEmailsListQueryKey: (owner: { type: string; id: number }) => ['outbound-emails', owner.type, owner.id, 'list'],
  outboundEmailQueryKey: (owner: { type: string; id: number }, emailId: number) => ['outbound-emails', owner.type, owner.id, 'detail', emailId],
  outboundEmailComposeContextQueryKey: (owner: { type: string; id: number }) => ['outbound-emails', owner.type, owner.id, 'compose-context'],
  listOutboundEmails: (...args: unknown[]) => listOutboundEmailsMock(...args),
  createOutboundEmailDraft: (...args: unknown[]) => createOutboundEmailDraftMock(...args),
  fetchOutboundEmail: (...args: unknown[]) => fetchOutboundEmailMock(...args),
  updateOutboundEmail: (...args: unknown[]) => updateOutboundEmailMock(...args),
  deleteOutboundEmail: (...args: unknown[]) => deleteOutboundEmailMock(...args),
  sendOutboundEmail: (...args: unknown[]) => sendOutboundEmailMock(...args),
  fetchOutboundEmailComposeContext: (...args: unknown[]) => fetchOutboundEmailComposeContextMock(...args),
  renderOutboundEmailTemplate: (...args: unknown[]) => renderOutboundEmailTemplateMock(...args),
  uploadOutboundEmailAttachment: (...args: unknown[]) => uploadOutboundEmailAttachmentMock(...args),
  importOutboundEmailAttachments: (...args: unknown[]) => importOutboundEmailAttachmentsMock(...args),
  removeOutboundEmailAttachment: (...args: unknown[]) => removeOutboundEmailAttachmentMock(...args),
  downloadOutboundEmailAttachment: (...args: unknown[]) => downloadOutboundEmailAttachmentMock(...args),
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

function renderPanel(props: { canSend?: boolean } = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(withProviders(<OutboundEmailsPanel owner={WORK_ORDER_OWNER} canSend={props.canSend ?? true} />, client))
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
  listOutboundEmailsMock.mockReset()
  createOutboundEmailDraftMock.mockReset()
  fetchOutboundEmailMock.mockReset()
  updateOutboundEmailMock.mockReset()
  deleteOutboundEmailMock.mockReset()
  sendOutboundEmailMock.mockReset()
  fetchOutboundEmailComposeContextMock.mockReset()
  fetchOutboundEmailComposeContextMock.mockResolvedValue(composeContext())
})

afterEach(() => {
  vi.clearAllMocks()
})

describe('OutboundEmailsPanel', () => {
  it('lists the history with status badges and opens the read-only detail on a non-draft row', async () => {
    listOutboundEmailsMock.mockResolvedValue({
      data: [listItem({ id: 1, status: 'sent', subject: 'Quote follow-up' })],
      meta: { current_page: 1, last_page: 1, total: 1 },
    })
    fetchOutboundEmailMock.mockResolvedValue(email({ id: 1, status: 'sent', subject: 'Quote follow-up', to: ['client@example.com'], body: '<p>Hi</p>' }))

    renderPanel()

    await screen.findByText('Quote follow-up')
    expect(screen.getByText('Sent')).toBeInTheDocument()

    fireEvent.click(screen.getByText('Quote follow-up'))

    const dialog = await screen.findByRole('dialog', { name: 'Email details' })
    await waitFor(() => expect(within(dialog).getByText('client@example.com')).toBeInTheDocument())
    expect(fetchOutboundEmailMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1)
  })

  it('reopens the composer on a draft row, hydrating it from the loaded draft', async () => {
    listOutboundEmailsMock.mockResolvedValue({
      data: [listItem({ id: 5, status: 'draft', subject: 'Draft subject', sent_at: null })],
      meta: { current_page: 1, last_page: 1, total: 1 },
    })
    fetchOutboundEmailMock.mockResolvedValue(email({ id: 5, status: 'draft', subject: 'Draft subject' }))

    renderPanel()

    fireEvent.click(await screen.findByText('Draft subject'))

    const dialog = await screen.findByRole('dialog', { name: 'Edit draft' })
    await waitFor(() => expect(within(dialog).getByDisplayValue('Draft subject')).toBeInTheDocument())
  })

  it('"New email" creates an empty draft and opens the composer on it', async () => {
    listOutboundEmailsMock.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
    createOutboundEmailDraftMock.mockResolvedValue(email({ id: 200 }))
    fetchOutboundEmailMock.mockResolvedValue(email({ id: 200 }))

    renderPanel()

    await screen.findByText('No emails for this work order yet.')
    fireEvent.click(screen.getByRole('button', { name: 'New email' }))

    await screen.findByRole('dialog', { name: 'New email' })
    expect(createOutboundEmailDraftMock).toHaveBeenCalledWith(WORK_ORDER_OWNER)
    await waitFor(() => expect(fetchOutboundEmailMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 200))
  })

  it('does not render "New email" without send permission', async () => {
    listOutboundEmailsMock.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })

    renderPanel({ canSend: false })

    await screen.findByText('No emails for this work order yet.')
    expect(screen.queryByRole('button', { name: 'New email' })).not.toBeInTheDocument()
  })

  it('silently deletes an untouched fresh draft on close, without asking to confirm', async () => {
    listOutboundEmailsMock.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
    createOutboundEmailDraftMock.mockResolvedValue(email({ id: 200 }))
    fetchOutboundEmailMock.mockResolvedValue(email({ id: 200 }))
    deleteOutboundEmailMock.mockResolvedValue(undefined)

    renderPanel()

    await screen.findByText('No emails for this work order yet.')
    fireEvent.click(screen.getByRole('button', { name: 'New email' }))
    const dialog = await screen.findByRole('dialog', { name: 'New email' })
    await waitFor(() => expect(fetchOutboundEmailMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 200))

    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(deleteOutboundEmailMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 200))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'New email' })).not.toBeInTheDocument())
  })

  it('asks for confirmation when closing a draft with unsaved changes, and keeps it open on cancel', async () => {
    listOutboundEmailsMock.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
    createOutboundEmailDraftMock.mockResolvedValue(email({ id: 200 }))
    fetchOutboundEmailMock.mockResolvedValue(email({ id: 200 }))

    renderPanel()

    await screen.findByText('No emails for this work order yet.')
    fireEvent.click(screen.getByRole('button', { name: 'New email' }))
    const dialog = await screen.findByRole('dialog', { name: 'New email' })
    await waitFor(() => expect(fetchOutboundEmailMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 200))

    fireEvent.change(within(dialog).getByLabelText('Subject'), { target: { value: 'Typed subject' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    const confirmDialog = await screen.findByRole('alertdialog')
    expect(within(confirmDialog).getByText('Unsaved changes')).toBeInTheDocument()

    fireEvent.click(within(confirmDialog).getByRole('button', { name: 'Cancel' }))
    expect(deleteOutboundEmailMock).not.toHaveBeenCalled()
    expect(screen.getByRole('dialog', { name: 'New email' })).toBeInTheDocument()

    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))
    const confirmDialogAgain = await screen.findByRole('alertdialog')
    fireEvent.click(within(confirmDialogAgain).getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'New email' })).not.toBeInTheDocument())
    expect(deleteOutboundEmailMock).not.toHaveBeenCalled()
    expect(updateOutboundEmailMock).not.toHaveBeenCalled()
  })
})
