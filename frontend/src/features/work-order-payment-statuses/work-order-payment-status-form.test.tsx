import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { WorkOrderPaymentStatusForm } from '@/features/work-order-payment-statuses/work-order-payment-status-form'
import type { WorkOrderPaymentStatusDetailWithPermissions } from '@/features/work-order-payment-statuses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * The form is create-only, a replica of the detail with its rows closed
 * (spec 0235); the in-place edits live in `work-order-payment-status-detail.test.tsx`.
 */

const createWorkOrderPaymentStatusMock = vi.fn()

vi.mock('@/features/work-order-payment-statuses/api', () => ({
  createWorkOrderPaymentStatus: (...args: unknown[]) => createWorkOrderPaymentStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/** Every field resolves as visible+editable (the `MetaField` fallback, since `fields` is empty). */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: FULL_ACCESS_PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

/** Picks a token from the `ColorTokenPicker` popover by its localized name. */
function pickColor(name: string) {
  openRow('workOrderPaymentStatuses.form.color')
  fireEvent.click(screen.getByRole('button', { name: /choose a color/i }))
  fireEvent.click(screen.getByRole('option', { name }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function workOrderPaymentStatus(): WorkOrderPaymentStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Paid',
    description: 'Cashed in full',
    color: 'blue',
    sort_order: 3,
    is_active: true,
    allows_delivery: false,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createWorkOrderPaymentStatusMock.mockReset()
})

describe('WorkOrderPaymentStatusForm — create', () => {
  it('starts with every row closed, with no order input', () => {
    render(<WorkOrderPaymentStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    openRow('workOrderPaymentStatuses.form.name')
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Order/)).not.toBeInTheDocument()
  })

  it('shows the errors on the closed rows and does not call the API when name and color are missing', async () => {
    render(<WorkOrderPaymentStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')
    expect(screen.getByText('Color is required.')).toBeInTheDocument()
    expect(createWorkOrderPaymentStatusMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save, without sort_order', async () => {
    createWorkOrderPaymentStatusMock.mockResolvedValue(workOrderPaymentStatus())
    const onSuccess = vi.fn()

    render(<WorkOrderPaymentStatusForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('workOrderPaymentStatuses.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Paid' } })
    pickColor('Green')
    save()

    await waitFor(() => expect(createWorkOrderPaymentStatusMock).toHaveBeenCalledTimes(1))
    expect(createWorkOrderPaymentStatusMock).toHaveBeenCalledWith({ name: 'Paid', description: null, color: 'green', is_active: true, allows_delivery: false })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(workOrderPaymentStatus()))
  })

  it('submits allows_delivery when switched on', async () => {
    createWorkOrderPaymentStatusMock.mockResolvedValue(workOrderPaymentStatus())

    render(<WorkOrderPaymentStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('workOrderPaymentStatuses.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Paid' } })
    pickColor('Green')
    openRow('workOrderPaymentStatuses.form.allowsDelivery')
    fireEvent.click(screen.getByRole('switch', { name: /Can be delivered/ }))
    save()

    await waitFor(() => expect(createWorkOrderPaymentStatusMock).toHaveBeenCalledTimes(1))
    expect(createWorkOrderPaymentStatusMock).toHaveBeenCalledWith(expect.objectContaining({ allows_delivery: true }))
  })
})
