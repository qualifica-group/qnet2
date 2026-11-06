import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { AxiosError, AxiosHeaders } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OutboundEmailComposerDialog } from '@/features/outbound-emails/outbound-email-composer-dialog'
import { OutboundEmailDetailDialog } from '@/features/outbound-emails/outbound-email-detail-dialog'
import { OutboundEmailList } from '@/features/outbound-emails/outbound-email-list'
import { todayIso } from '@/features/outbound-emails/outbound-email-recurrence-schema'
import type {
  ComposeContext,
  OutboundEmail,
  OutboundEmailListItem,
  OutboundEmailRecurrence,
} from '@/features/outbound-emails/types'

/**
 * Spec 0362 FE: the composer's "Ricorrenza" section and "Programma" action
 * (AC-021/022), and the scheduled-series readout in detail and list (AC-023).
 */

const OWNER = { type: 'work-orders', id: 42 } as const
const DAY_MS = 86_400_000

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => <span>template-stub</span>,
}))

const fetchOutboundEmailMock = vi.fn()
const updateOutboundEmailMock = vi.fn()
const scheduleOutboundEmailMock = vi.fn()
const unscheduleOutboundEmailMock = vi.fn()
const listOutboundEmailsMock = vi.fn()
const fetchComposeContextMock = vi.fn()

vi.mock('@/features/outbound-emails/api', () => ({
  outboundEmailsListQueryKey: (owner: { type: string; id: number }) => ['outbound-emails', owner.type, owner.id, 'list'],
  outboundEmailQueryKey: (owner: { type: string; id: number }, emailId: number) => ['outbound-emails', owner.type, owner.id, 'detail', emailId],
  outboundEmailComposeContextQueryKey: (owner: { type: string; id: number }) => ['outbound-emails', owner.type, owner.id, 'compose-context'],
  fetchOutboundEmail: (...args: unknown[]) => fetchOutboundEmailMock(...args),
  updateOutboundEmail: (...args: unknown[]) => updateOutboundEmailMock(...args),
  scheduleOutboundEmail: (...args: unknown[]) => scheduleOutboundEmailMock(...args),
  unscheduleOutboundEmail: (...args: unknown[]) => unscheduleOutboundEmailMock(...args),
  listOutboundEmails: (...args: unknown[]) => listOutboundEmailsMock(...args),
  fetchOutboundEmailComposeContext: (...args: unknown[]) => fetchComposeContextMock(...args),
  deleteOutboundEmail: vi.fn(),
  sendOutboundEmail: vi.fn(),
  renderOutboundEmailTemplate: vi.fn(),
  uploadOutboundEmailAttachment: vi.fn(),
  importOutboundEmailAttachments: vi.fn(),
  removeOutboundEmailAttachment: vi.fn(),
  downloadOutboundEmailAttachment: vi.fn(),
}))

function recurrence(overrides: Partial<OutboundEmailRecurrence> = {}): OutboundEmailRecurrence {
  return {
    frequency: 'weekly',
    interval: 1,
    weekdays: [1],
    month_mode: null,
    month_day: null,
    ordinal: null,
    ordinal_weekday: null,
    year_month: null,
    workdays_only: false,
    starts_on: '2027-01-04',
    ends_on: '2027-02-01',
    next_send_on: '2027-01-11',
    last_sent_on: '2027-01-04',
    sent_count: 1,
    end_reason: null,
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
    to: ['client@example.com'],
    cc: [],
    bcc: [],
    subject: 'Weekly update',
    body: '<p>Hi</p>',
    attachments: [],
    attachments_total_size: 0,
    queued_at: null,
    sent_at: null,
    failed_at: null,
    error_message: null,
    created_at: '2026-09-20T09:00:00.000Z',
    updated_at: '2026-09-20T09:00:00.000Z',
    recurrence: null,
    recurrence_source_id: null,
    can: { update: true, delete: true, send: true, schedule: true, unschedule: false },
    ...overrides,
  }
}

function listItem(overrides: Partial<OutboundEmailListItem> = {}): OutboundEmailListItem {
  return {
    id: 1,
    status: 'sent',
    sender: { id: 9, name: 'Mario Rossi' },
    to: ['client@example.com'],
    subject: 'Weekly update',
    attachments_count: 0,
    sent_at: '2026-09-20T10:00:00.000Z',
    failed_at: null,
    updated_at: '2026-09-20T10:00:00.000Z',
    next_send_on: null,
    is_recurring_copy: false,
    ...overrides,
  }
}

const composeContext: ComposeContext = {
  sender: { name: 'Mario Rossi', email: 'mario.rossi@example.com' },
  sender_options: ['mario.rossi@example.com'],
  recipient_suggestions: [],
  documents: [],
  quote_pdf_available: false,
  max_total_attachments_kb: 25600,
}

function httpError(status: number, data: Record<string, unknown>): AxiosError {
  return new AxiosError('failed', String(status), undefined, undefined, {
    status,
    statusText: 'error',
    headers: new AxiosHeaders(),
    config: { headers: new AxiosHeaders() },
    data,
  })
}

function withProviders(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{ui}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function renderComposer() {
  const onOpenChange = vi.fn()
  render(withProviders(<OutboundEmailComposerDialog owner={OWNER} emailId={1} justCreated={false} open onOpenChange={onOpenChange} />))
  return { onOpenChange }
}

function isoInDays(days: number): string {
  return todayIso(new Date(Date.now() + days * DAY_MS))
}

async function fillWeeklyRule(starts: string, ends: string | null) {
  fireEvent.click(await screen.findByRole('switch', { name: /repeat this email/i }))
  fireEvent.click(await screen.findByRole('checkbox', { name: 'Monday' }))
  fireEvent.change(screen.getByLabelText(/first send/i), { target: { value: starts } })
  if (ends) {
    fireEvent.change(screen.getByLabelText(/end date/i), { target: { value: ends } })
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchComposeContextMock.mockResolvedValue(composeContext)
})

afterEach(() => {
  vi.clearAllMocks()
  window.localStorage.clear()
})

describe('composer recurrence (AC-021, AC-022)', () => {
  it('switch off leaves the plain composer: "Send", no rule fields', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    renderComposer()

    expect(await screen.findByRole('switch', { name: /repeat this email/i })).not.toBeChecked()
    expect(screen.getByRole('button', { name: 'Send' })).toBeInTheDocument()
    expect(screen.queryByLabelText(/end date/i)).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Schedule' })).not.toBeInTheDocument()
  })

  it('hides the section when the email cannot be scheduled (invoices, purchase orders)', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email({ can: { update: true, delete: true, send: true, schedule: false, unschedule: false } }))
    renderComposer()

    await screen.findByDisplayValue('Weekly update')
    expect(screen.queryByRole('switch', { name: /repeat this email/i })).not.toBeInTheDocument()
  })

  it('switch on shows the rule fields, a sentence preview and "Schedule" instead of "Send"', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    renderComposer()

    await fillWeeklyRule(isoInDays(1), isoInDays(30))

    expect(await screen.findByRole('button', { name: 'Schedule' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Send' })).not.toBeInTheDocument()
    expect(screen.getByText(/Every week on Monday/)).toBeInTheDocument()
  })

  it('blocks "Schedule" without an end date and wires the accessible error', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    renderComposer()

    await fillWeeklyRule(isoInDays(1), null)
    fireEvent.click(screen.getByRole('button', { name: 'Schedule' }))

    const error = await screen.findByText('The end date is required.')
    expect(error).toHaveAttribute('role', 'alert')
    expect(screen.getByLabelText(/end date/i)).toHaveAttribute('aria-invalid', 'true')
    expect(updateOutboundEmailMock).not.toHaveBeenCalled()
    expect(scheduleOutboundEmailMock).not.toHaveBeenCalled()
  })

  it('saves the draft, then schedules with the exact rule payload and closes', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    updateOutboundEmailMock.mockResolvedValue(email())
    scheduleOutboundEmailMock.mockResolvedValue(email({ status: 'scheduled', recurrence: recurrence() }))
    const { onOpenChange } = renderComposer()
    const starts = isoInDays(1)
    const ends = isoInDays(30)

    await fillWeeklyRule(starts, ends)
    fireEvent.click(screen.getByRole('button', { name: 'Schedule' }))

    await waitFor(() =>
      expect(scheduleOutboundEmailMock).toHaveBeenCalledWith(OWNER, 1, {
        frequency: 'weekly',
        interval: 1,
        weekdays: [1],
        workdays_only: false,
        starts_on: starts,
        ends_on: ends,
      }),
    )
    expect(updateOutboundEmailMock).toHaveBeenCalledTimes(1)
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
  })

  it('maps a 422 on recurrence.ends_on onto the field and keeps the composer open', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    updateOutboundEmailMock.mockResolvedValue(email())
    scheduleOutboundEmailMock.mockRejectedValue(
      httpError(422, { success: false, message: 'Invalid', errors: { 'recurrence.ends_on': ['Server: end date too far.'] } }),
    )
    const { onOpenChange } = renderComposer()

    await fillWeeklyRule(isoInDays(1), isoInDays(30))
    fireEvent.click(screen.getByRole('button', { name: 'Schedule' }))

    expect(await screen.findByText('Server: end date too far.')).toHaveAttribute('role', 'alert')
    expect(onOpenChange).not.toHaveBeenCalledWith(false)
  })

  it('shows the server sentence of a 409 as a message and keeps the composer open', async () => {
    fetchOutboundEmailMock.mockResolvedValue(email())
    updateOutboundEmailMock.mockResolvedValue(email())
    scheduleOutboundEmailMock.mockRejectedValue(
      httpError(409, { success: false, message: 'The work order is no longer in progress.' }),
    )
    const { onOpenChange } = renderComposer()

    await fillWeeklyRule(isoInDays(1), isoInDays(30))
    fireEvent.click(screen.getByRole('button', { name: 'Schedule' }))

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith('The work order is no longer in progress.'))
    expect(onOpenChange).not.toHaveBeenCalledWith(false)
  })
})

describe('scheduled series readout (AC-023)', () => {
  function renderDetail(onUnscheduled = vi.fn()) {
    render(withProviders(<OutboundEmailDetailDialog owner={OWNER} emailId={1} open onOpenChange={vi.fn()} onUnscheduled={onUnscheduled} />))
    return { onUnscheduled }
  }

  it('shows the rule sentence, period, next send, count and "Back to draft" with can.unschedule', async () => {
    fetchOutboundEmailMock.mockResolvedValue(
      email({
        status: 'scheduled',
        recurrence: recurrence(),
        can: { update: false, delete: false, send: false, schedule: false, unschedule: true },
      }),
    )
    unscheduleOutboundEmailMock.mockResolvedValue(email({ recurrence: recurrence({ next_send_on: null }) }))
    const { onUnscheduled } = renderDetail()

    expect(await screen.findByText(/Every week on Monday/)).toBeInTheDocument()
    expect(screen.getByText('Scheduled')).toBeInTheDocument()
    expect(screen.getByText('Next send')).toBeInTheDocument()
    expect(screen.getByText('Sends made')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Back to draft' }))
    await waitFor(() => expect(unscheduleOutboundEmailMock).toHaveBeenCalledWith(OWNER, 1))
    await waitFor(() => expect(onUnscheduled).toHaveBeenCalledWith(1))
  })

  it('an ended series shows its reason and no "Back to draft" without can.unschedule', async () => {
    fetchOutboundEmailMock.mockResolvedValue(
      email({
        status: 'schedule_ended',
        recurrence: recurrence({ next_send_on: null, end_reason: 'owner_closed' }),
        can: { update: false, delete: false, send: false, schedule: false, unschedule: false },
      }),
    )
    renderDetail()

    expect(await screen.findByText('The work order is no longer in progress')).toBeInTheDocument()
    expect(screen.getByText('Schedule ended')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Back to draft' })).not.toBeInTheDocument()
  })

  it('the list marks a recurring copy with an icon and a scheduled row with its next send', async () => {
    listOutboundEmailsMock.mockResolvedValue({
      data: [
        listItem({ id: 1, is_recurring_copy: true }),
        listItem({ id: 2, status: 'scheduled', next_send_on: '2027-01-11', sent_at: null }),
      ],
      meta: { current_page: 1, last_page: 1, total: 2 },
    })
    render(withProviders(<OutboundEmailList owner={OWNER} onSelect={vi.fn()} />))

    expect(await screen.findByRole('img', { name: 'Recurring send' })).toBeInTheDocument()
    expect(screen.getAllByRole('img', { name: 'Recurring send' })).toHaveLength(1)
    expect(screen.getByText(/Next send:/)).toBeInTheDocument()
  })
})
