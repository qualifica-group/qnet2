import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RecordFieldChangeRequests } from '@/features/field-change-requests/record-field-change-requests'
import type { FieldChangeRequestResource } from '@/features/field-change-requests/types'

/**
 * AC-048: the record's change-requests section lists status, field, current/
 * requested value, motivation, requester and date, in the exact server
 * order (never re-sorted client-side). Loading/empty/error states, and a 403
 * degrading to a silent no-render rather than breaking the host page.
 *
 * User directive 2026-08-04: only requests still `pending` are listed, and an
 * actor allowed to decide gets Approve/Reject right on the card.
 */

const fetchFieldChangeRequestsForRecordMock = vi.fn()
const approveFieldChangeRequestMock = vi.fn()
const rejectFieldChangeRequestMock = vi.fn()

vi.mock('@/features/field-change-requests/api', () => ({
  fetchFieldChangeRequestsForRecord: (...args: unknown[]) =>
    fetchFieldChangeRequestsForRecordMock(...args),
  approveFieldChangeRequest: (...args: unknown[]) => approveFieldChangeRequestMock(...args),
  rejectFieldChangeRequest: (...args: unknown[]) => rejectFieldChangeRequestMock(...args),
}))

vi.mock('sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}))

function buildRequest(overrides: Partial<FieldChangeRequestResource> = {}): FieldChangeRequestResource {
  return {
    id: 1,
    resource: 'request-management',
    resource_label: 'navigation.requestManagement',
    subject_id: 345,
    subject_label: 'OPP_345',
    subject_path: '/request-management/345',
    field: 'requestManagement.columns.source',
    field_label: 'requestManagement.columns.source',
    current_value: 3,
    current_label: 'Campagna Google',
    requested_value: 7,
    requested_label: 'Passaparola',
    reason: 'Referral confermato.',
    status: 'pending',
    requested_by: { id: 8, name: 'Mario Rossi' },
    requested_at: '2026-08-03T10:12:00.000000Z',
    handled_by: null,
    handled_at: null,
    handling_note: null,
    can: { approve: false, reject: false },
    ...overrides,
  }
}

function renderSection() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onHandled = vi.fn()
  const view = render(
    <QueryClientProvider client={client}>
      <RecordFieldChangeRequests resource="request-management" subjectId={345} onHandled={onHandled} />
    </QueryClientProvider>,
  )

  return { ...view, onHandled }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchFieldChangeRequestsForRecordMock.mockReset()
  approveFieldChangeRequestMock.mockReset()
  rejectFieldChangeRequestMock.mockReset()
})

describe('RecordFieldChangeRequests — fields (AC-048)', () => {
  it('lists status, field, current/requested value, reason, requester and date', async () => {
    fetchFieldChangeRequestsForRecordMock.mockResolvedValue([buildRequest()])

    renderSection()

    await waitFor(() => expect(screen.getByText('Campagna Google')).toBeInTheDocument())
    expect(screen.getByText('Passaparola')).toBeInTheDocument()
    expect(screen.getByText('Referral confermato.')).toBeInTheDocument()
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
    expect(screen.getByText('Pending')).toBeInTheDocument()
    expect(fetchFieldChangeRequestsForRecordMock).toHaveBeenCalledWith('request-management', 345)
  })

  it('does not re-sort: renders the requests in the exact server order', async () => {
    fetchFieldChangeRequestsForRecordMock.mockResolvedValue([
      buildRequest({ id: 1, field_label: 'a.field' }),
      buildRequest({ id: 2, field_label: 'b.field' }),
    ])

    renderSection()

    await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(2))
    const items = screen.getAllByRole('listitem')
    expect(items[0]).toHaveTextContent('a.field')
    expect(items[1]).toHaveTextContent('b.field')
  })

  it('lists the pending requests only: an approved/rejected one never shows on the record', async () => {
    fetchFieldChangeRequestsForRecordMock.mockResolvedValue([
      buildRequest({ id: 1, field_label: 'pending.field' }),
      buildRequest({ id: 2, field_label: 'approved.field', status: 'approved' }),
      buildRequest({ id: 3, field_label: 'rejected.field', status: 'rejected' }),
    ])

    renderSection()

    await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(1))
    expect(screen.getAllByRole('listitem')[0]).toHaveTextContent('pending.field')
  })
})

describe('RecordFieldChangeRequests — deciding from the card', () => {
  it('omits both buttons when the actor may neither approve nor reject', async () => {
    fetchFieldChangeRequestsForRecordMock.mockResolvedValue([buildRequest()])

    renderSection()

    await waitFor(() => expect(screen.getByText('Passaparola')).toBeInTheDocument())
    expect(screen.queryByRole('button', { name: 'Approve' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reject' })).not.toBeInTheDocument()
  })

  it('approves from the card and reports the resolved request to the host', async () => {
    const updated = buildRequest({ status: 'approved', can: { approve: false, reject: false } })
    fetchFieldChangeRequestsForRecordMock.mockResolvedValue([
      buildRequest({ can: { approve: true, reject: true } }),
    ])
    approveFieldChangeRequestMock.mockResolvedValue(updated)

    const { onHandled } = renderSection()

    await waitFor(() => expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Approve' }))
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(approveFieldChangeRequestMock).toHaveBeenCalledWith(1, null))
    await waitFor(() => expect(onHandled).toHaveBeenCalledWith(updated))
  })

  it('rejects from the card', async () => {
    fetchFieldChangeRequestsForRecordMock.mockResolvedValue([
      buildRequest({ can: { approve: true, reject: true } }),
    ])
    rejectFieldChangeRequestMock.mockResolvedValue(buildRequest({ status: 'rejected' }))

    renderSection()

    await waitFor(() => expect(screen.getByRole('button', { name: 'Reject' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Reject' }))
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(rejectFieldChangeRequestMock).toHaveBeenCalledWith(1, null))
    expect(approveFieldChangeRequestMock).not.toHaveBeenCalled()
  })
})

describe('RecordFieldChangeRequests — loading/empty/error states', () => {
  it('shows a skeleton while loading', () => {
    fetchFieldChangeRequestsForRecordMock.mockReturnValue(new Promise(() => {}))

    const { container } = renderSection()

    expect(container.querySelectorAll('[data-slot="skeleton"]').length).toBeGreaterThan(0)
  })

  it('shows the empty state with no requests', async () => {
    fetchFieldChangeRequestsForRecordMock.mockResolvedValue([])

    renderSection()

    await waitFor(() =>
      expect(
        screen.getByText('No change request awaiting a decision on this record.'),
      ).toBeInTheDocument(),
    )
  })

  it('renders nothing on a 403 (not authorized), without breaking the page', async () => {
    fetchFieldChangeRequestsForRecordMock.mockRejectedValue({
      isAxiosError: true,
      response: { status: 403, data: { success: false, message: 'Forbidden' } },
    })

    const { container } = renderSection()

    await waitFor(() => expect(fetchFieldChangeRequestsForRecordMock).toHaveBeenCalled())
    await waitFor(() => expect(container).toBeEmptyDOMElement())
  })

  it('shows a retry affordance on any other error', async () => {
    fetchFieldChangeRequestsForRecordMock.mockRejectedValue(new Error('network down'))

    renderSection()

    await waitFor(() =>
      expect(
        screen.getByText('Unable to load the change requests. Please try again.'),
      ).toBeInTheDocument(),
    )
    // `common.retry` is a pre-existing, already-merged global key (unlike the
    // `fieldChangeRequests.*` ones this microtask introduces), so it resolves
    // to its real translation rather than falling back to the raw key.
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })
})
