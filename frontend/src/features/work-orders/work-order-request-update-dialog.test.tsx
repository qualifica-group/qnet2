import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { requestWorkOrderUpdate } from '@/features/work-orders/api'
import { WorkOrderRequestUpdateDialog } from '@/features/work-orders/work-order-request-update-dialog'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, requestWorkOrderUpdate: vi.fn() }
})

const label = (key: string) => i18n.t(key)
const SUBMIT = 'workOrders.actions.requestUpdate.submit'

interface RecipientOverrides {
  participants?: WorkOrderDetailWithPermissions['participants']
  supervisors?: WorkOrderDetailWithPermissions['supervisors']
}

function serverError(status: 403 | 422, message: string, errors?: Record<string, string[]>): AxiosError {
  const error = new AxiosError('failed')
  error.response = {
    status,
    statusText: 'error',
    data: { success: false, message, errors },
    headers: {},
    config: { headers: new AxiosHeaders() },
  }
  return error
}

function renderDialog(overrides: RecipientOverrides = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const workOrder = {
    id: 9,
    participants: [
      { id: 1, name: 'Bruno Bianchi', position: 1 },
      { id: 2, name: 'Carla Conti', position: 2 },
    ],
    supervisors: [
      { id: 2, name: 'Carla Conti' },
      { id: 3, name: 'Ada Alberti' },
    ],
    ...overrides,
  } as WorkOrderDetailWithPermissions
  const onOpenChange = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <WorkOrderRequestUpdateDialog open onOpenChange={onOpenChange} workOrder={workOrder} />
    </QueryClientProvider>,
  )
  return { onOpenChange }
}

function radio(labelKey: string, count: number) {
  return screen.getByRole('radio', { name: `${label(labelKey)} (${count})` })
}

function fillMessageAndSubmit(message = 'a che punto sei?') {
  fireEvent.change(screen.getByRole('textbox', { name: /Message/ }), { target: { value: message } })
  fireEvent.click(screen.getByRole('button', { name: label(SUBMIT) }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(requestWorkOrderUpdate).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('WorkOrderRequestUpdateDialog — groups (spec 0352 AC-012)', () => {
  it('shows the three groups with counts, participants selected, all deduplicated', () => {
    renderDialog()

    expect(radio('workOrders.actions.requestUpdate.targetParticipants', 2)).toBeChecked()
    expect(radio('workOrders.actions.requestUpdate.targetSupervisors', 2)).not.toBeChecked()
    expect(radio('workOrders.actions.requestUpdate.targetAll', 3)).not.toBeChecked()
  })

  it('states that supervisors get a copy only when there are supervisors', () => {
    renderDialog()
    expect(screen.getByText(label('workOrders.actions.requestUpdate.targetParticipantsHint'))).toBeInTheDocument()
  })

  it('omits the copy hint and disables the empty supervisors group', () => {
    renderDialog({ supervisors: [] })

    expect(screen.queryByText(label('workOrders.actions.requestUpdate.targetParticipantsHint'))).not.toBeInTheDocument()
    expect(radio('workOrders.actions.requestUpdate.targetSupervisors', 0)).toBeDisabled()
  })

  it('shows the empty text and disables submit with no recipients at all', () => {
    renderDialog({ participants: [], supervisors: [] })

    expect(screen.getByText(label('workOrders.actions.requestUpdate.recipientsEmpty'))).toBeInTheDocument()
    expect(screen.getByRole('button', { name: label(SUBMIT) })).toBeDisabled()
  })
})

describe('WorkOrderRequestUpdateDialog — message and submit (spec 0352 AC-012)', () => {
  it('rejects a message under 3 characters without calling the API', async () => {
    renderDialog()
    fillMessageAndSubmit('hi')

    await screen.findByText(label('workOrders.actions.requestUpdate.messageTooShort'))
    expect(requestWorkOrderUpdate).not.toHaveBeenCalled()
  })

  it('sends the default target with the trimmed message, toasts and closes', async () => {
    vi.mocked(requestWorkOrderUpdate).mockResolvedValueOnce(undefined)
    const { onOpenChange } = renderDialog()

    fillMessageAndSubmit('  Fammi sapere  ')

    await vi.waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
    expect(requestWorkOrderUpdate).toHaveBeenCalledWith(9, { target: 'participants', message: 'Fammi sapere' })
    expect(toast.success).toHaveBeenCalledWith(label('workOrders.actions.requestUpdate.success'))
  })

  it('sends the chosen target ("all")', async () => {
    vi.mocked(requestWorkOrderUpdate).mockResolvedValueOnce(undefined)
    renderDialog()

    fireEvent.click(radio('workOrders.actions.requestUpdate.targetAll', 3))
    fillMessageAndSubmit()

    await vi.waitFor(() =>
      expect(requestWorkOrderUpdate).toHaveBeenCalledWith(9, { target: 'all', message: 'a che punto sei?' }),
    )
  })
})

describe('WorkOrderRequestUpdateDialog — server errors (spec 0352 AC-013)', () => {
  it('wires a 422 on message inline', async () => {
    vi.mocked(requestWorkOrderUpdate).mockRejectedValueOnce(
      serverError(422, 'invalid', { message: ['The message must be at least 3 characters.'] }),
    )
    renderDialog()

    fillMessageAndSubmit()

    expect(await screen.findByText('The message must be at least 3 characters.')).toBeInTheDocument()
  })

  it('toasts the server reason of a 422 without field errors', async () => {
    vi.mocked(requestWorkOrderUpdate).mockRejectedValueOnce(
      serverError(422, 'This work order is already completed or closed.'),
    )
    renderDialog()

    fillMessageAndSubmit()

    await vi.waitFor(() => expect(toast.error).toHaveBeenCalledWith('This work order is already completed or closed.'))
  })

  it('toasts a 403', async () => {
    vi.mocked(requestWorkOrderUpdate).mockRejectedValueOnce(serverError(403, 'Not allowed.'))
    renderDialog()

    fillMessageAndSubmit()

    await vi.waitFor(() => expect(toast.error).toHaveBeenCalledWith('Not allowed.'))
  })
})
