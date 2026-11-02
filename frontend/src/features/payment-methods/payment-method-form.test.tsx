import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PaymentMethodForm } from '@/features/payment-methods/payment-method-form'
import type { PaymentMethodDetailWithPermissions } from '@/features/payment-methods/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Wave 3 of the "no edit pages" plan (spec 0236): the form is create-only, a
 * replica of the detail with its rows closed plus the create-only `code`; the
 * in-place edits live in `payment-method-detail.test.tsx`.
 */

const createPaymentMethodMock = vi.fn()

vi.mock('@/features/payment-methods/api', () => ({
  createPaymentMethod: (...args: unknown[]) => createPaymentMethodMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/** Every field editable, matching the create-context metadata (`code.editable === true` while `$model === null`). */
const CREATE_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: CREATE_PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function paymentMethod(): PaymentMethodDetailWithPermissions {
  return {
    id: 9,
    name: 'Bank transfer',
    code: 'bank_transfer',
    payment_method_code: 'MP05',
    description: 'Standard bank transfer',
    payment_instructions: null,
    payment_days: 30,
    installments_count: 1,
    days_between_installments: 0,
    end_of_month: false,
    end_of_month_extra_days: null,
    vat_allocation: 'split',
    payment_condition: 'TP02',
    excluded_month_1: null,
    excluded_month_2: null,
    sort_order: 10,
    is_active: true,
    created_at: null as unknown as string,
    updated_at: null as unknown as string,
    permissions: CREATE_PERMISSIONS,
  }
}

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createPaymentMethodMock.mockReset()
})

describe('PaymentMethodForm — create (spec 0068, AC-102/AC-103/AC-104)', () => {
  it('starts with every row closed, each opening on its own control, with no order row', () => {
    render(<PaymentMethodForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    openRow('paymentMethods.form.name')
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    openRow('paymentMethods.form.code')
    expect(screen.getByLabelText(/^Code/)).toBeInTheDocument()
    openRow('paymentMethods.form.paymentMethodCode')
    expect(screen.getByLabelText(/^Payment method code/)).toBeInTheDocument()
    openRow('paymentMethods.form.description')
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
    openRow('paymentMethods.form.paymentInstructions')
    expect(screen.getByLabelText(/^Payment instructions/)).toBeInTheDocument()
    openRow('paymentMethods.form.paymentDays')
    expect(screen.getByLabelText(/^Payment days/)).toBeInTheDocument()
    openRow('paymentMethods.form.isActive')
    expect(screen.getByRole('switch', { name: 'Active' })).toBeChecked()
    expect(screen.queryByLabelText(/^Order/)).not.toBeInTheDocument()
  })

  it('shows the error on the closed row, wires the aria triad and does not call the API when name is empty', async () => {
    render(<PaymentMethodForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')

    openRow('paymentMethods.form.name')
    const nameInput = screen.getByLabelText(/^Name/)
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(nameInput.getAttribute('aria-describedby')).toBeTruthy()
    expect(createPaymentMethodMock).not.toHaveBeenCalled()
  })

  it('shows the gap error on the closed schedule row when several installments have none', async () => {
    render(<PaymentMethodForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('paymentMethods.form.installmentsCount')
    fireEvent.change(screen.getByLabelText(/^Number of installments/), { target: { value: '3' } })
    fireEvent.change(screen.getByLabelText(/^Days between installments/), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
    save()

    expect(
      await screen.findByText('Days between installments are required with more than one installment.'),
    ).toBeInTheDocument()
    expect(createPaymentMethodMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save', async () => {
    createPaymentMethodMock.mockResolvedValue(paymentMethod())
    const onSuccess = vi.fn()

    render(<PaymentMethodForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('paymentMethods.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Bank transfer' } })
    openRow('paymentMethods.form.code')
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'bank_transfer' } })
    openRow('paymentMethods.form.paymentDays')
    fireEvent.change(screen.getByLabelText(/^Payment days/), { target: { value: '30' } })
    save()

    await waitFor(() => expect(createPaymentMethodMock).toHaveBeenCalledTimes(1))
    expect(createPaymentMethodMock).toHaveBeenCalledWith({
      name: 'Bank transfer',
      code: 'bank_transfer',
      payment_method_code: null,
      description: null,
      payment_instructions: null,
      payment_days: 30,
      installments_count: 1,
      days_between_installments: 0,
      end_of_month: false,
      end_of_month_extra_days: null,
      vat_allocation: 'split',
      payment_condition: 'TP02',
      excluded_month_1: null,
      excluded_month_2: null,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(paymentMethod()))
  })
})
