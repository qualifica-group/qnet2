import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { WorkOrderPaymentStatusForm } from '@/features/work-order-payment-statuses/work-order-payment-status-form'
import type { WorkOrderPaymentStatusDetailWithPermissions } from '@/features/work-order-payment-statuses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createWorkOrderPaymentStatusMock = vi.fn()
const updateWorkOrderPaymentStatusMock = vi.fn()

vi.mock('@/features/work-order-payment-statuses/api', () => ({
  createWorkOrderPaymentStatus: (...args: unknown[]) => createWorkOrderPaymentStatusMock(...args),
  updateWorkOrderPaymentStatus: (...args: unknown[]) => updateWorkOrderPaymentStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Every field resolves as visible+editable (the `MetaField` fallback, since
 * `fields` is empty) — not about authorization metadata.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/work-order-payment-statuses/use-work-order-payment-status-form-meta', () => ({
  useWorkOrderPaymentStatusFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function workOrderPaymentStatus(
  overrides: Partial<WorkOrderPaymentStatusDetailWithPermissions> = {},
): WorkOrderPaymentStatusDetailWithPermissions {
  return {
    id: 9,
    name: 'Paid',
    description: 'Paid in full',
    color: 'green',
    sort_order: 10,
    is_active: true,
    allows_delivery: false,
    created_at: null as unknown as string,
    updated_at: null as unknown as string,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createWorkOrderPaymentStatusMock.mockReset()
  updateWorkOrderPaymentStatusMock.mockReset()
})

describe('WorkOrderPaymentStatusForm — create/edit (spec 0201)', () => {
  it('renders name, description, color and is_active fields in create mode, with no order input (D-3)', () => {
    render(
      <WorkOrderPaymentStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /choose a color/i })).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Active' })).toBeChecked()
    expect(screen.queryByLabelText(/^Order/)).not.toBeInTheDocument()
  })

  it('renders the allows_delivery switch, hydrated in edit mode', () => {
    render(
      <WorkOrderPaymentStatusForm
        mode={{ type: 'edit', workOrderPaymentStatus: workOrderPaymentStatus({ allows_delivery: true }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('switch', { name: 'Can be delivered' })).toBeChecked()
    expect(screen.queryByRole('combobox', { name: /^Group/ })).not.toBeInTheDocument()
  })

  it('shows an inline error and does not call the API when name is empty and color is unchosen', async () => {
    render(
      <WorkOrderPaymentStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Name is required.')).toBeInTheDocument())
    expect(screen.getByText('Color is required.')).toBeInTheDocument()
    expect(createWorkOrderPaymentStatusMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save, without sort_order', async () => {
    createWorkOrderPaymentStatusMock.mockResolvedValue(workOrderPaymentStatus())
    const onSuccess = vi.fn()

    render(
      <WorkOrderPaymentStatusForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Paid' } })
    fireEvent.click(screen.getByRole('button', { name: /choose a color/i }))
    fireEvent.click(screen.getByRole('option', { name: 'Green' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createWorkOrderPaymentStatusMock).toHaveBeenCalledTimes(1))
    expect(createWorkOrderPaymentStatusMock).toHaveBeenCalledWith({
      name: 'Paid',
      description: null,
      color: 'green',
      is_active: true,
      allows_delivery: false,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(workOrderPaymentStatus()))
  })

  it('hydrates name, description, color and is_active in edit mode', () => {
    render(
      <WorkOrderPaymentStatusForm
        mode={{ type: 'edit', workOrderPaymentStatus: workOrderPaymentStatus({ is_active: false }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Paid')
    expect(screen.getByLabelText(/^Description/)).toHaveValue('Paid in full')
    expect(screen.getByRole('button', { name: /Green/ })).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Active' })).not.toBeChecked()
  })

  it('submits only the changed name on a partial update', async () => {
    updateWorkOrderPaymentStatusMock.mockResolvedValue(workOrderPaymentStatus({ name: 'Unpaid' }))

    render(
      <WorkOrderPaymentStatusForm
        mode={{ type: 'edit', workOrderPaymentStatus: workOrderPaymentStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Unpaid' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateWorkOrderPaymentStatusMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateWorkOrderPaymentStatusMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Unpaid' })
  })
})
