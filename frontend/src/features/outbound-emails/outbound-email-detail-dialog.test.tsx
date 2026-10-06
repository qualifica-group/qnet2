import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OutboundEmailDetailDialog } from '@/features/outbound-emails/outbound-email-detail-dialog'
import type { OutboundEmail } from '@/features/outbound-emails/types'

/** Read-only rendering of a queued/sent/failed email (AC-019) and its "Reinvia" action (AC-014). */

const WORK_ORDER_OWNER = { type: 'work-orders', id: 42 } as const

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchOutboundEmailMock = vi.fn()
const sendOutboundEmailMock = vi.fn()
const downloadOutboundEmailAttachmentMock = vi.fn()
const saveBlobMock = vi.fn()

vi.mock('@/features/outbound-emails/api', () => ({
  outboundEmailQueryKey: (owner: { type: string; id: number }, emailId: number) => ['outbound-emails', owner.type, owner.id, 'detail', emailId],
  fetchOutboundEmail: (...args: unknown[]) => fetchOutboundEmailMock(...args),
  sendOutboundEmail: (...args: unknown[]) => sendOutboundEmailMock(...args),
  downloadOutboundEmailAttachment: (...args: unknown[]) => downloadOutboundEmailAttachmentMock(...args),
}))

vi.mock('@/lib/download', () => ({
  saveBlob: (...args: unknown[]) => saveBlobMock(...args),
}))

function email(overrides: Partial<OutboundEmail> = {}): OutboundEmail {
  return {
    id: 1,
    status: 'sent',
    purpose: null,
    email_template_id: null,
    sender: { id: 9, name: 'Mario Rossi' },
    from_address: 'mario.rossi@example.com',
    to: ['client@example.com'],
    cc: [],
    bcc: [],
    subject: 'Quote follow-up',
    body: '<p>Please find attached.</p>',
    attachments: [],
    attachments_total_size: 0,
    queued_at: null,
    sent_at: '2026-09-20T10:00:00.000Z',
    failed_at: null,
    error_message: null,
    created_at: '2026-09-20T09:00:00.000Z',
    updated_at: '2026-09-20T10:00:00.000Z',
    can: { update: false, delete: false, send: false },
    ...overrides,
  }
}

function renderDetail() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onOpenChange = vi.fn()
  render(withProviders(<OutboundEmailDetailDialog owner={WORK_ORDER_OWNER} emailId={1} open onOpenChange={onOpenChange} />, client))
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
  sendOutboundEmailMock.mockReset()
  downloadOutboundEmailAttachmentMock.mockReset()
  saveBlobMock.mockReset()
})

afterEach(() => {
  vi.clearAllMocks()
})

describe('OutboundEmailDetailDialog', () => {
  it('renders a sent email read-only with its downloadable attachments, and no resend action', async () => {
    fetchOutboundEmailMock.mockResolvedValue(
      email({ attachments: [{ id: 30, original_name: 'quote.pdf', mime_type: 'application/pdf', size: 2048 }] }),
    )
    downloadOutboundEmailAttachmentMock.mockResolvedValue(new Blob(['binary']))

    renderDetail()

    await screen.findByText('Quote follow-up')
    expect(screen.getByText('Please find attached.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Resend' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Download' }))
    await waitFor(() => expect(downloadOutboundEmailAttachmentMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1, 30))
    await waitFor(() => expect(saveBlobMock).toHaveBeenCalledWith(expect.any(Blob), 'quote.pdf'))
  })

  it('shows the error reason and a resend action for a failed email the actor can send', async () => {
    fetchOutboundEmailMock.mockResolvedValue(
      email({ status: 'failed', error_message: 'Mailbox not found.', can: { update: false, delete: false, send: true } }),
    )
    sendOutboundEmailMock.mockResolvedValue(email({ status: 'queued' }))

    renderDetail()

    await screen.findByText('Mailbox not found.')
    fireEvent.click(screen.getByRole('button', { name: 'Resend' }))

    await waitFor(() => expect(sendOutboundEmailMock).toHaveBeenCalledWith(WORK_ORDER_OWNER, 1))
  })

  it('does not offer a resend action for a failed email the actor cannot send', async () => {
    fetchOutboundEmailMock.mockResolvedValue(
      email({ status: 'failed', error_message: 'Mailbox not found.', can: { update: false, delete: false, send: false } }),
    )

    renderDetail()

    await screen.findByText('Mailbox not found.')
    expect(screen.queryByRole('button', { name: 'Resend' })).not.toBeInTheDocument()
  })
})
