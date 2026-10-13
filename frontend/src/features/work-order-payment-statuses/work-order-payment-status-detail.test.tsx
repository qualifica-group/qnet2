import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { WorkOrderPaymentStatusDetailView } from '@/features/work-order-payment-statuses/work-order-payment-status-detail'
import type { WorkOrderPaymentStatusDetailWithPermissions } from '@/features/work-order-payment-statuses/types'

/**
 * The detail shows the record's fields; every row but the server-managed order
 * edits in place (spec 0235), each save a PATCH of that field alone. The
 * Activity Log gate is exercised on the `permissions.actions.view_activity` prop.
 */

const activityLogSectionMock = vi.fn()
const updateWorkOrderPaymentStatusMock = vi.fn()

vi.mock('@/features/work-order-payment-statuses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-order-payment-statuses/api')>()),
  updateWorkOrderPaymentStatus: (...args: unknown[]) => updateWorkOrderPaymentStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function workOrderPaymentStatus(overrides: Partial<WorkOrderPaymentStatusDetailWithPermissions> = {}): WorkOrderPaymentStatusDetailWithPermissions {
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
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

/** One QueryClient per test: the in-place rows save through React Query. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  updateWorkOrderPaymentStatusMock.mockReset()
})

describe('WorkOrderPaymentStatusDetailView — detail fields', () => {
  it('shows the fields and the timestamps', () => {
    render(<WorkOrderPaymentStatusDetailView workOrderPaymentStatus={workOrderPaymentStatus()} />)

    expect(screen.getByRole('heading', { name: 'Paid' })).toBeInTheDocument()
    expect(screen.getByText('Cashed in full')).toBeInTheDocument()
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })
})

describe('WorkOrderPaymentStatusDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(
      <WorkOrderPaymentStatusDetailView
        workOrderPaymentStatus={workOrderPaymentStatus({
          permissions: { ...workOrderPaymentStatus().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'work-order-payment-statuses', id: 7 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<WorkOrderPaymentStatusDetailView workOrderPaymentStatus={workOrderPaymentStatus()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('WorkOrderPaymentStatusDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    render(<WorkOrderPaymentStatusDetailView workOrderPaymentStatus={workOrderPaymentStatus()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('workOrderPaymentStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('workOrderPaymentStatuses.form.isActive')).toBeInTheDocument()
    expect(pencilOf('workOrderPaymentStatuses.form.allowsDelivery')).toBeInTheDocument()
  })

  it('Save sends only the changed field, never sort_order', async () => {
    updateWorkOrderPaymentStatusMock.mockResolvedValue(workOrderPaymentStatus({ name: 'Renamed' }))
    render(<WorkOrderPaymentStatusDetailView workOrderPaymentStatus={workOrderPaymentStatus()} />)

    fireEvent.click(pencilOf('workOrderPaymentStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateWorkOrderPaymentStatusMock).toHaveBeenCalledWith(7, { name: 'Renamed' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<WorkOrderPaymentStatusDetailView workOrderPaymentStatus={workOrderPaymentStatus()} />)

    fireEvent.click(pencilOf('workOrderPaymentStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateWorkOrderPaymentStatusMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateWorkOrderPaymentStatusMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<WorkOrderPaymentStatusDetailView workOrderPaymentStatus={workOrderPaymentStatus()} />)

    fireEvent.click(pencilOf('workOrderPaymentStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    const base = workOrderPaymentStatus()
    render(
      <WorkOrderPaymentStatusDetailView
        workOrderPaymentStatus={workOrderPaymentStatus({
          permissions: { ...base.permissions, resource: { ...base.permissions.resource, update: false } },
        })}
      />,
    )

    expect(pencilOf('workOrderPaymentStatuses.form.name')).not.toBeInTheDocument()
    expect(pencilOf('workOrderPaymentStatuses.form.color')).not.toBeInTheDocument()
  })

  it('Save sends allows_delivery alone when only that switch changes', async () => {
    updateWorkOrderPaymentStatusMock.mockResolvedValue(workOrderPaymentStatus({ allows_delivery: true }))
    render(<WorkOrderPaymentStatusDetailView workOrderPaymentStatus={workOrderPaymentStatus()} />)

    fireEvent.click(pencilOf('workOrderPaymentStatuses.form.allowsDelivery') as HTMLElement)
    fireEvent.click(screen.getByRole('switch', { name: /Can be delivered/ }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateWorkOrderPaymentStatusMock).toHaveBeenCalledWith(7, { allows_delivery: true }))
  })
})
