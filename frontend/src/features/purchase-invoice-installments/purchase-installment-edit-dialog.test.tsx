import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchaseInstallmentEditDialog } from '@/features/purchase-invoice-installments/purchase-installment-edit-dialog'
import {
  EDITABLE_FIELD,
  axiosError,
  purchaseDetail,
} from '@/features/purchase-invoice-installments/purchase-installment-fixtures'
import type {
  PurchaseInstallmentDetail,
  PurchaseInstallmentUpdatePayload,
} from '@/features/purchase-invoice-installments/types'

const getMock = vi.fn<(id: number) => Promise<PurchaseInstallmentDetail>>()
const updateMock = vi.fn<(id: number, payload: PurchaseInstallmentUpdatePayload) => Promise<PurchaseInstallmentDetail>>()

vi.mock('@/features/purchase-invoice-installments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoice-installments/api')>()),
  getPurchaseInstallment: (id: number) => getMock(id),
  updatePurchaseInstallment: (id: number, payload: PurchaseInstallmentUpdatePayload) => updateMock(id, payload),
}))
vi.mock('@/features/for-select/api', () => ({
  fetchForSelect: () =>
    Promise.resolve({
      items: [
        { id: 1, label: 'RiBa 30', subtitle: 'riba30', meta: { payment_method_code: 'MP12' } },
        { id: 2, label: 'Bonifico', subtitle: 'bank', meta: { payment_method_code: 'MP05' } },
        { id: 3, label: 'Senza codice', subtitle: 'none', meta: {} },
      ],
      export_link: null,
      pagination: { total: 3, offset: 0, limit: 100, total_pages: 1 },
    }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderDialog() {
  const onSaved = vi.fn()
  const onClose = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <PurchaseInstallmentEditDialog installmentId={7} onClose={onClose} onSaved={onSaved} />
    </QueryClientProvider>,
  )
  return { onSaved, onClose }
}

const methodSelect = () => screen.findByRole('combobox', { name: 'Method code' })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset().mockResolvedValue(purchaseDetail())
  updateMock.mockReset().mockResolvedValue(purchaseDetail())
})

describe('PurchaseInstallmentEditDialog (spec 0211 AC-015)', () => {
  it('sends the PATCH with the new date and the method picked from the select, then refreshes the grid', async () => {
    const { onSaved, onClose } = renderDialog()

    fireEvent.change(await screen.findByLabelText('Due date'), { target: { value: '2026-12-15' } })
    fireEvent.pointerDown(await methodSelect(), { button: 0, ctrlKey: false, pointerType: 'mouse' })
    fireEvent.click(await screen.findByRole('option', { name: 'Bonifico (MP05)' }))
    expect(screen.queryByRole('option', { name: /Senza codice/ })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(7, { due_date: '2026-12-15', payment_method_code: 'MP05' }),
    )
    await waitFor(() => expect(onSaved).toHaveBeenCalled())
    expect(onClose).toHaveBeenCalled()
  })

  it('clears the method with "None" and sends null', async () => {
    renderDialog()

    fireEvent.pointerDown(await methodSelect(), { button: 0, ctrlKey: false, pointerType: 'mouse' })
    fireEvent.click(await screen.findByRole('option', { name: 'None' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(7, { due_date: '2026-11-30', payment_method_code: null }))
  })

  it('renders non-editable fields read-only and omits them from the PATCH', async () => {
    getMock.mockResolvedValue(
      purchaseDetail({
        field_permissions: {
          due_date: EDITABLE_FIELD,
          payment_method_code: { visible: true, editable: false, required: false },
        },
      }),
    )
    renderDialog()

    expect(await methodSelect()).toBeDisabled()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))
    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(7, { due_date: '2026-11-30' }))
  })

  it('hides a field the actor cannot see', async () => {
    getMock.mockResolvedValue(
      purchaseDetail({
        field_permissions: {
          due_date: EDITABLE_FIELD,
          payment_method_code: { visible: false, editable: false, required: false },
        },
      }),
    )
    renderDialog()

    await screen.findByLabelText('Due date')
    expect(screen.queryByRole('combobox', { name: 'Method code' })).not.toBeInTheDocument()
  })

  it('offers no Save button without update rights', async () => {
    getMock.mockResolvedValue(purchaseDetail({ abilities: { update: false, pay: true } }))
    renderDialog()

    await screen.findByLabelText('Due date')
    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
    expect(screen.getByText('You do not have permission to edit this installment.')).toBeInTheDocument()
  })

  it('shows the 409 conflict as an alert and keeps the dialog open', async () => {
    updateMock.mockRejectedValue(axiosError(409, { message: 'Paid installments cannot be edited.' }))
    const { onSaved } = renderDialog()

    await screen.findByLabelText('Due date')
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('already paid')
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
