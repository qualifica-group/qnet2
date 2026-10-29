import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { fetchWorkOrder, updateWorkOrder } from '@/features/work-orders/api'
import { WorkOrderForceCloseDialog } from '@/features/work-orders/work-order-force-close-dialog'

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, updateWorkOrder: vi.fn(), fetchWorkOrder: vi.fn() }
})

const label = (key: string) => i18n.t(key)

function status(id: number, name: string, group: string, systemKey: string | null) {
  return { id, name, description: null, color: null, group, system_key: systemKey, progress_percentage: null }
}

const RECORD = {
  id: 7,
  open_tasks_count: 0,
  work_order_workflow_statuses: [
    status(1, 'Open', 'working', 'open'),
    status(2, 'Done', 'completed', 'completed'),
    status(30, 'Lost', 'closed', null),
    status(31, 'Closed', 'closed', 'closed'),
  ],
}

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return render(<WorkOrderForceCloseDialog workOrderId={7} onOpenChange={vi.fn()} />, { wrapper })
}

const statusCombobox = () => screen.findByRole('combobox', { name: new RegExp(label('workOrders.actions.forceClose.status')) })
const reasonBox = () => screen.getByRole('textbox', { name: new RegExp(label('workOrders.form.forceCloseReason')) })
const submit = () => screen.getByRole('button', { name: label('workOrders.actions.forceClose.submit') })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(fetchWorkOrder).mockReset().mockResolvedValue(RECORD as never)
  vi.mocked(updateWorkOrder).mockReset()
})

describe('WorkOrderForceCloseDialog — closing status', () => {
  it('preselects the keyed closed row and offers only closed-group statuses', async () => {
    renderDialog()

    const combobox = await statusCombobox()
    expect(combobox).toHaveTextContent('Closed')

    fireEvent.click(combobox)
    const options = within(await screen.findByRole('listbox')).getAllByRole('option')
    expect(options.map((option) => option.textContent)).toEqual(['Lost', 'Closed'])
  })

  it('sends force_close_status_id with the reason', async () => {
    vi.mocked(updateWorkOrder).mockResolvedValue(RECORD as never)
    renderDialog()
    await statusCombobox()

    fireEvent.change(reasonBox(), { target: { value: 'Persa' } })
    fireEvent.click(submit())

    await waitFor(() =>
      expect(updateWorkOrder).toHaveBeenCalledWith(7, {
        is_force_closed: true,
        force_close_reason: 'Persa',
        force_close_status_id: 31,
      }),
    )
  })

  it('shows a server 422 under the status field', async () => {
    vi.mocked(updateWorkOrder).mockRejectedValue({
      isAxiosError: true,
      response: { status: 422, data: { errors: { force_close_status_id: ['Stato non valido.'] } } },
    })
    renderDialog()
    await statusCombobox()

    fireEvent.change(reasonBox(), { target: { value: 'Persa' } })
    fireEvent.click(submit())

    expect(await screen.findByText('Stato non valido.')).toBeInTheDocument()
  })

  it('blocks the submit client-side when no closing status is picked', async () => {
    vi.mocked(fetchWorkOrder).mockResolvedValue({
      ...RECORD,
      work_order_workflow_statuses: [status(30, 'Lost', 'closed', null)],
    } as never)
    renderDialog()
    await statusCombobox()

    fireEvent.change(reasonBox(), { target: { value: 'Persa' } })
    fireEvent.click(submit())

    expect(await screen.findByText(label('workOrders.form.forceCloseStatusRequired'))).toBeInTheDocument()
    expect(updateWorkOrder).not.toHaveBeenCalled()
  })
})
