import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { PaymentMethodForm } from '@/features/payment-methods/payment-method-form'
import type { PaymentMethodDetailWithPermissions } from '@/features/payment-methods/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

const createPaymentMethodMock = vi.fn()
const updatePaymentMethodMock = vi.fn()

vi.mock('@/features/payment-methods/api', () => ({
  createPaymentMethod: (...args: unknown[]) => createPaymentMethodMock(...args),
  updatePaymentMethod: (...args: unknown[]) => updatePaymentMethodMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const READONLY: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

/** Every field editable, matching the create-context metadata (`code.editable === true` while `$model === null`). */
const CREATE_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

/** `code` readonly, everything else editable, matching an existing record's metadata (D-3). */
const EDIT_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {
    name: EDITABLE,
    code: READONLY,
    payment_method_code: EDITABLE,
    description: EDITABLE,
    payment_instructions: EDITABLE,
    payment_days: EDITABLE,
    is_active: EDITABLE,
  },
  actions: {},
}

let metaPermissions: ResourcePermissions = CREATE_PERMISSIONS

vi.mock('@/features/payment-methods/use-payment-method-form-meta', () => ({
  usePaymentMethodFormMeta: () => ({ status: 'ready', permissions: metaPermissions }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function paymentMethod(
  overrides: Partial<PaymentMethodDetailWithPermissions> = {},
): PaymentMethodDetailWithPermissions {
  return {
    id: 9,
    name: 'Bank transfer',
    code: 'bank_transfer',
    payment_method_code: 'MP05',
    description: 'Standard bank transfer',
    payment_instructions: 'Use the company IBAN',
    payment_days: 30,
    sort_order: 10,
    is_active: true,
    created_at: null as unknown as string,
    updated_at: null as unknown as string,
    permissions: EDIT_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createPaymentMethodMock.mockReset()
  updatePaymentMethodMock.mockReset()
  metaPermissions = CREATE_PERMISSIONS
})

describe('PaymentMethodForm — create (spec 0068, AC-102/AC-103/AC-104)', () => {
  it('renders name, code, payment_method_code, description, payment_instructions, payment_days and is_active, with no order input', () => {
    render(
      <PaymentMethodForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Code/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Payment method code/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Payment instructions/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Payment days/)).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Active' })).toBeChecked()
    expect(screen.queryByLabelText(/^Order/)).not.toBeInTheDocument()
  })

  it('shows an accessible inline error and does not call the API when name is empty', async () => {
    render(
      <PaymentMethodForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'bank_transfer' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const nameInput = await screen.findByLabelText(/^Name/)
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const describedBy = nameInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()
    const message = screen.getByText('Name is required.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
    expect(createPaymentMethodMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save', async () => {
    createPaymentMethodMock.mockResolvedValue(paymentMethod())
    const onSuccess = vi.fn()

    render(
      <PaymentMethodForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Bank transfer' } })
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'bank_transfer' } })
    fireEvent.change(screen.getByLabelText(/^Payment days/), { target: { value: '30' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createPaymentMethodMock).toHaveBeenCalledTimes(1))
    expect(createPaymentMethodMock).toHaveBeenCalledWith({
      name: 'Bank transfer',
      code: 'bank_transfer',
      payment_method_code: null,
      description: null,
      payment_instructions: null,
      payment_days: 30,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(paymentMethod()))
  })
})

describe('PaymentMethodForm — edit (spec 0068, AC-105/AC-106)', () => {
  it('hydrates every field and renders code disabled', () => {
    metaPermissions = EDIT_PERMISSIONS

    render(
      <PaymentMethodForm
        mode={{ type: 'edit', paymentMethod: paymentMethod() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Bank transfer')
    expect(screen.getByLabelText(/^Code/)).toHaveValue('bank_transfer')
    expect(screen.getByLabelText(/^Code/)).toBeDisabled()
    // The fiscal code stays editable after create, unlike `code` (D-3).
    expect(screen.getByLabelText(/^Payment method code/)).toHaveValue('MP05')
    expect(screen.getByLabelText(/^Payment method code/)).not.toBeDisabled()
    expect(screen.getByLabelText(/^Description/)).toHaveValue('Standard bank transfer')
    expect(screen.getByLabelText(/^Payment days/)).toHaveValue(30)
  })

  it('submits only the changed field on a partial update, never code', async () => {
    metaPermissions = EDIT_PERMISSIONS
    updatePaymentMethodMock.mockResolvedValue(paymentMethod({ name: 'Wire transfer' }))

    render(
      <PaymentMethodForm
        mode={{ type: 'edit', paymentMethod: paymentMethod() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Wire transfer' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updatePaymentMethodMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updatePaymentMethodMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Wire transfer' })
    expect(payload).not.toHaveProperty('code')
  })

  it('maps a server 422 onto the matching form field', async () => {
    metaPermissions = EDIT_PERMISSIONS
    updatePaymentMethodMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { name: ['Name already taken.'] },
        },
      } as never),
    )

    render(
      <PaymentMethodForm
        mode={{ type: 'edit', paymentMethod: paymentMethod() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Duplicate name' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Name already taken.')).toBeInTheDocument())
  })
})
