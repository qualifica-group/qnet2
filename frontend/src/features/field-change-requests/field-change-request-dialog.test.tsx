import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { FieldChangeRequestDialog } from '@/features/field-change-requests/field-change-request-dialog'
import type { RequestFieldChangeParams } from '@/features/field-change-requests/types'

/**
 * AC-044: confirming the dialog sends the frozen `POST /field-change-requests`
 * payload and shows a success toast; cancelling sends nothing at all. The
 * dialog is deliberately generic (AC-054) — this suite exercises it with a
 * request-management/Fonte-shaped prompt only as an example caller.
 */

const createFieldChangeRequestMock = vi.fn()

vi.mock('@/features/field-change-requests/api', () => ({
  createFieldChangeRequest: (...args: unknown[]) => createFieldChangeRequestMock(...args),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()

vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

function buildPrompt(overrides: Partial<RequestFieldChangeParams> = {}): RequestFieldChangeParams {
  return {
    resource: 'request-management',
    subjectId: 345,
    field: 'source_id',
    requestedValue: 7,
    currentLabel: 'Campagna Google',
    requestedLabel: 'Passaparola',
    fieldLabelKey: 'requestManagement.columns.source',
    ...overrides,
  }
}

function renderDialog(prompt: RequestFieldChangeParams | null, onClose = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <FieldChangeRequestDialog prompt={prompt} onClose={onClose} />
    </QueryClientProvider>,
  )
  return { onClose }
}

beforeEach(() => {
  createFieldChangeRequestMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('FieldChangeRequestDialog (spec 0078, AC-044)', () => {
  it('shows the current and requested value', () => {
    renderDialog(buildPrompt())

    expect(screen.getByText('Campagna Google')).toBeInTheDocument()
    expect(screen.getByText('Passaparola')).toBeInTheDocument()
  })

  it('posts the frozen payload and shows a success toast on confirm', async () => {
    createFieldChangeRequestMock.mockResolvedValue({})
    renderDialog(buildPrompt())

    fireEvent.click(screen.getByRole('button', { name: 'fieldChangeRequests.dialog.submit' }))

    await waitFor(() => expect(createFieldChangeRequestMock).toHaveBeenCalledTimes(1))
    expect(createFieldChangeRequestMock.mock.calls[0][0]).toEqual({
      resource: 'request-management',
      subject_id: 345,
      field: 'source_id',
      requested_value: 7,
      reason: null,
    })
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledTimes(1))
  })

  it('sends the free-text reason when the user provides one', async () => {
    createFieldChangeRequestMock.mockResolvedValue({})
    renderDialog(buildPrompt())

    fireEvent.change(screen.getByRole('textbox'), {
      target: { value: 'Il cliente ha confermato di arrivare da un referral.' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'fieldChangeRequests.dialog.submit' }))

    await waitFor(() => expect(createFieldChangeRequestMock).toHaveBeenCalledTimes(1))
    expect(createFieldChangeRequestMock.mock.calls[0][0]).toMatchObject({
      reason: 'Il cliente ha confermato di arrivare da un referral.',
    })
  })

  it('does not call the API when the user cancels, and closes the dialog', () => {
    const { onClose } = renderDialog(buildPrompt())

    fireEvent.click(screen.getByRole('button', { name: 'fieldChangeRequests.dialog.cancel' }))

    expect(createFieldChangeRequestMock).not.toHaveBeenCalled()
    expect(onClose).toHaveBeenCalledTimes(1)
  })

  it('shows the server error message on failure', async () => {
    createFieldChangeRequestMock.mockRejectedValue({
      isAxiosError: true,
      response: { status: 422, data: { success: false, message: 'Already writable directly.' } },
    })
    renderDialog(buildPrompt())

    fireEvent.click(screen.getByRole('button', { name: 'fieldChangeRequests.dialog.submit' }))

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('Already writable directly.'))
  })

  it('renders nothing interactive while closed', () => {
    renderDialog(null)

    expect(
      screen.queryByRole('button', { name: 'fieldChangeRequests.dialog.submit' }),
    ).not.toBeInTheDocument()
    expect(createFieldChangeRequestMock).not.toHaveBeenCalled()
  })
})
