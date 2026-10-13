import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { PurchaseOrderStatusDetailView } from '@/features/purchase-order-statuses/purchase-order-status-detail'
import type { PurchaseOrderStatusDetailWithPermissions } from '@/features/purchase-order-statuses/types'

/**
 * Spec 0248 AC-010: the detail edits name, color and icon IN PLACE, each save
 * a PATCH of that field alone (never `system_key` nor `sort_order`); the enum
 * key and the order are read-only; there is no Delete.
 */

const updateMock = vi.fn()

vi.mock('@/features/purchase-order-statuses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-order-statuses/api')>()),
  updatePurchaseOrderStatus: (...args: unknown[]) => updateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

function status(overrides: Partial<PurchaseOrderStatusDetailWithPermissions> = {}): PurchaseOrderStatusDetailWithPermissions {
  return {
    id: 3,
    system_key: 'approved',
    name: 'Green light',
    color: 'green',
    icon: 'star',
    sort_order: 2,
    permissions: {
      resource: { view: true, create: false, update: true, delete: false, export: true, import: false },
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
  updateMock.mockReset()
})

describe('PurchaseOrderStatusDetailView', () => {
  it('shows name, color token, icon, system value and order, without a Delete action', () => {
    render(<PurchaseOrderStatusDetailView status={status()} />)

    expect(screen.getByRole('heading', { name: 'Green light' })).toBeInTheDocument()
    expect(screen.getByText('Green')).toBeInTheDocument()
    expect(screen.getByText('star')).toBeInTheDocument()
    expect(screen.getByText('approved')).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /delete/i })).not.toBeInTheDocument()
  })

  it('edits only name, color and icon: the system value and the order have no pencil', () => {
    render(<PurchaseOrderStatusDetailView status={status()} />)

    expect(pencilOf('purchaseOrderStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('purchaseOrderStatuses.form.color')).toBeInTheDocument()
    expect(pencilOf('purchaseOrderStatuses.form.icon')).toBeInTheDocument()
    expect(pencilOf('purchaseOrderStatuses.detail.systemKey')).not.toBeInTheDocument()
    expect(pencilOf('purchaseOrderStatuses.detail.sort_order')).not.toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updateMock.mockResolvedValue(status({ name: 'Renamed' }))
    render(<PurchaseOrderStatusDetailView status={status()} />)

    fireEvent.click(pencilOf('purchaseOrderStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(3, { name: 'Renamed' }))
  })

  it('maps a server 422 onto the open row', async () => {
    updateMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<PurchaseOrderStatusDetailView status={status()} />)

    fireEvent.click(pencilOf('purchaseOrderStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    const base = status()
    render(
      <PurchaseOrderStatusDetailView
        status={status({ permissions: { ...base.permissions, resource: { ...base.permissions.resource, update: false } } })}
      />,
    )

    expect(pencilOf('purchaseOrderStatuses.form.name')).not.toBeInTheDocument()
    expect(pencilOf('purchaseOrderStatuses.form.color')).not.toBeInTheDocument()
  })

  it('mounts the activity section only with view_activity', () => {
    const base = status()
    const { unmount } = render(<PurchaseOrderStatusDetailView status={base} />)
    expect(screen.queryByText('activity-log-section')).not.toBeInTheDocument()
    unmount()

    render(<PurchaseOrderStatusDetailView status={status({ permissions: { ...base.permissions, actions: { view_activity: true } } })} />)
    expect(screen.getByText('Activity log')).toBeInTheDocument()
  })
})
