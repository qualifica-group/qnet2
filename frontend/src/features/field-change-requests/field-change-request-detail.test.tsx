import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { FieldChangeRequestDetailView } from '@/features/field-change-requests/field-change-request-detail'
import type { FieldChangeRequestResource } from '@/features/field-change-requests/types'

/**
 * AC-047: the Approve/Reject buttons exist in the DOM only when the
 * resource's own `can.approve`/`can.reject` are `true` (the UI hides, the
 * backend authorizes). AC-049: on a 409 conflict the server's own message is
 * shown and the request stays visibly `pending` — no local state change, no
 * refetch, no page reload.
 */

const approveFieldChangeRequestMock = vi.fn()
const rejectFieldChangeRequestMock = vi.fn()

vi.mock('@/features/field-change-requests/api', () => ({
  approveFieldChangeRequest: (...args: unknown[]) => approveFieldChangeRequestMock(...args),
  rejectFieldChangeRequest: (...args: unknown[]) => rejectFieldChangeRequestMock(...args),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()

vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

function buildRequest(overrides: Partial<FieldChangeRequestResource> = {}): FieldChangeRequestResource {
  return {
    id: 12,
    resource: 'request-management',
    resource_label: 'navigation.requestManagement',
    subject_id: 345,
    subject_label: 'OPP_345',
    subject_path: '/request-management/345',
    field: 'source_id',
    field_label: 'requestManagement.columns.source',
    current_value: 3,
    current_label: 'Campagna Google',
    requested_value: 7,
    requested_label: 'Passaparola',
    reason: 'Il cliente ha confermato di arrivare da un referral.',
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

function renderDetail(request: FieldChangeRequestResource, onChanged = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <FieldChangeRequestDetailView request={request} onChanged={onChanged} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
  return { onChanged }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  approveFieldChangeRequestMock.mockReset()
  rejectFieldChangeRequestMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('FieldChangeRequestDetailView — fields', () => {
  it('shows the record, field, values, requester, reason and status', () => {
    renderDetail(buildRequest())

    expect(screen.getByRole('link', { name: 'OPP_345' })).toHaveAttribute(
      'href',
      '/request-management/345',
    )
    expect(screen.getByText('Campagna Google')).toBeInTheDocument()
    expect(screen.getByText('Passaparola')).toBeInTheDocument()
    expect(screen.getByText('Il cliente ha confermato di arrivare da un referral.')).toBeInTheDocument()
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
  })

  it('shows the record as plain text when subject_path is not resolvable', () => {
    renderDetail(buildRequest({ subject_path: null }))

    expect(screen.queryByRole('link', { name: 'OPP_345' })).not.toBeInTheDocument()
    expect(screen.getByText('OPP_345')).toBeInTheDocument()
  })
})

describe('FieldChangeRequestDetailView — action gating (AC-047)', () => {
  it('renders both buttons when both can.approve and can.reject are true', () => {
    renderDetail(buildRequest({ can: { approve: true, reject: true } }))

    expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reject' })).toBeInTheDocument()
  })

  it('omits both buttons from the DOM when both can.approve and can.reject are false', () => {
    renderDetail(buildRequest({ can: { approve: false, reject: false } }))

    expect(screen.queryByRole('button', { name: 'Approve' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reject' })).not.toBeInTheDocument()
  })

  it('gates each button independently on its own can.* flag', () => {
    renderDetail(buildRequest({ can: { approve: true, reject: false } }))

    expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reject' })).not.toBeInTheDocument()
  })
})

describe('FieldChangeRequestDetailView — approve/reject (AC-049)', () => {
  it('approves, shows a success toast and reports the updated resource', async () => {
    const updated = buildRequest({ status: 'approved', can: { approve: false, reject: false } })
    approveFieldChangeRequestMock.mockResolvedValue(updated)
    const { onChanged } = renderDetail(buildRequest({ can: { approve: true, reject: true } }))

    fireEvent.click(screen.getByRole('button', { name: 'Approve' }))
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(approveFieldChangeRequestMock).toHaveBeenCalledWith(12, null))
    await waitFor(() => expect(onChanged).toHaveBeenCalledWith(updated))
    expect(toastSuccessMock).toHaveBeenCalledTimes(1)
  })

  it('shows the server 409 message and leaves the request visibly pending, without calling onChanged', async () => {
    approveFieldChangeRequestMock.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 409,
        data: { success: false, message: 'The current value has changed since the request was created.' },
      },
    })
    const { onChanged } = renderDetail(buildRequest({ can: { approve: true, reject: true } }))

    fireEvent.click(screen.getByRole('button', { name: 'Approve' }))
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'The current value has changed since the request was created.',
      ),
    )
    expect(onChanged).not.toHaveBeenCalled()
    // The status badge still reads "Pending" (`enums.field_change_request_status`),
    // proving no local state changed.
    expect(screen.getByText('Pending')).toBeInTheDocument()
  })

  it('rejects with the free-text note', async () => {
    const updated = buildRequest({ status: 'rejected', can: { approve: false, reject: false } })
    rejectFieldChangeRequestMock.mockResolvedValue(updated)
    renderDetail(buildRequest({ can: { approve: true, reject: true } }))

    fireEvent.click(screen.getByRole('button', { name: 'Reject' }))
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Not justified.' } })
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(rejectFieldChangeRequestMock).toHaveBeenCalledWith(12, 'Not justified.'))
    expect(approveFieldChangeRequestMock).not.toHaveBeenCalled()
  })
})
