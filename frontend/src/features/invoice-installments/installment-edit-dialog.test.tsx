import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { InstallmentEditDialog } from '@/features/invoice-installments/installment-edit-dialog'
import type { InstallmentDetail, InstallmentUpdatePayload } from '@/features/invoice-installments/types'

const getMock = vi.fn<(id: number) => Promise<InstallmentDetail>>()
const updateMock = vi.fn<(id: number, payload: InstallmentUpdatePayload) => Promise<InstallmentDetail>>()

vi.mock('@/features/invoice-installments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoice-installments/api')>()),
  getInstallment: (id: number) => getMock(id),
  updateInstallment: (id: number, payload: InstallmentUpdatePayload) => updateMock(id, payload),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const EDITABLE = { visible: true, editable: true, required: false }

function detail(overrides: Partial<InstallmentDetail> = {}): InstallmentDetail {
  return {
    id: 7,
    sequence: 2,
    due_date: '2026-11-30',
    amount: '400.00',
    payment_method_code: 'riba_30_60',
    collected_amount: null,
    collected_at: null,
    status: 'unpaid',
    residual_amount: '400.00',
    is_overdue: false,
    days_overdue: 0,
    invoice: {
      id: 3,
      number_label: '12/2026',
      document_date: '2026-10-01',
      total_amount: '800.00',
      customer: { id: 1, name: 'ACME Spa' },
      work_order: null,
      company_site: null,
      operational_site: null,
    },
    field_permissions: { due_date: EDITABLE, payment_method_code: EDITABLE },
    abilities: { update: true, collect: true, view_invoice: true },
    ...overrides,
  }
}

function axiosError(status: number, data: unknown): AxiosError {
  return new AxiosError('failed', String(status), undefined, undefined, { status, data } as AxiosResponse)
}

function renderDialog() {
  const onSaved = vi.fn()
  const onClose = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <InstallmentEditDialog installmentId={7} onClose={onClose} onSaved={onSaved} />
    </QueryClientProvider>,
  )
  return { onSaved, onClose }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset().mockResolvedValue(detail())
  updateMock.mockReset().mockResolvedValue(detail())
})

describe('InstallmentEditDialog (spec 0197 AC-019)', () => {
  it('sends the PATCH with both editable fields and refreshes the grid on success', async () => {
    const { onSaved, onClose } = renderDialog()

    fireEvent.change(await screen.findByLabelText('Due date'), { target: { value: '2026-12-15' } })
    fireEvent.change(screen.getByLabelText('Method code'), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(7, { due_date: '2026-12-15', payment_method_code: null }),
    )
    await waitFor(() => expect(onSaved).toHaveBeenCalled())
    expect(onClose).toHaveBeenCalled()
  })

  it('renders non-editable fields read-only and omits them from the PATCH', async () => {
    getMock.mockResolvedValue(
      detail({
        field_permissions: {
          due_date: EDITABLE,
          payment_method_code: { visible: true, editable: false, required: false },
        },
      }),
    )
    renderDialog()

    expect(await screen.findByLabelText('Method code')).toHaveAttribute('readonly')
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))
    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(7, { due_date: '2026-11-30' }))
  })

  it('hides a field the actor cannot see', async () => {
    getMock.mockResolvedValue(
      detail({
        field_permissions: {
          due_date: EDITABLE,
          payment_method_code: { visible: false, editable: false, required: false },
        },
      }),
    )
    renderDialog()

    await screen.findByLabelText('Due date')
    expect(screen.queryByLabelText('Method code')).not.toBeInTheDocument()
  })

  it('shows the 409 conflict as an alert and keeps the dialog open', async () => {
    updateMock.mockRejectedValue(axiosError(409, { message: 'Collected installments cannot be edited.' }))
    const { onSaved } = renderDialog()

    await screen.findByLabelText('Due date')
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('has a collection')
    expect(onSaved).not.toHaveBeenCalled()
  })

  it('maps a 422 onto the field with the accessible error wiring', async () => {
    updateMock.mockRejectedValue(
      axiosError(422, { errors: { due_date: ['The due date must be after the document date.'] } }),
    )
    renderDialog()

    const dueDate = await screen.findByLabelText('Due date')
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('after the document date')
    expect(dueDate).toHaveAttribute('aria-invalid', 'true')
    expect(dueDate.getAttribute('aria-describedby')).toContain(alert.id)
  })

  it('validates a missing date client-side without calling the server', async () => {
    renderDialog()

    fireEvent.change(await screen.findByLabelText('Due date'), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('The due date is required.')
    expect(updateMock).not.toHaveBeenCalled()
  })
})
