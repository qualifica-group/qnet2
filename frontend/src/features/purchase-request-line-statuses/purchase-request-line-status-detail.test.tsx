import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchaseRequestLineStatusDetailView } from '@/features/purchase-request-line-statuses/purchase-request-line-status-detail'
import type { PurchaseRequestLineStatusDetailWithPermissions } from '@/features/purchase-request-line-statuses/types'

/** Spec 0248 AC-010: the detail edits name, color and icon in place; no Delete, no sort_order or system_key in a write. */

const updateMock = vi.fn()

vi.mock('@/features/purchase-request-line-statuses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-request-line-statuses/api')>()),
  updatePurchaseRequestLineStatus: (...args: unknown[]) => updateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

function lineStatus(overrides: Partial<PurchaseRequestLineStatusDetailWithPermissions> = {}): PurchaseRequestLineStatusDetailWithPermissions {
  return {
    id: 3,
    system_key: 'ordered',
    name: 'Ordered',
    color: 'blue',
    icon: 'shopping-cart',
    sort_order: 3,
    permissions: {
      resource: { view: true, create: false, update: true, delete: false, export: true, import: false },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

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

describe('PurchaseRequestLineStatusDetailView (spec 0248 AC-010)', () => {
  it('shows the name, the read-only system key and the order', () => {
    render(<PurchaseRequestLineStatusDetailView purchaseRequestLineStatus={lineStatus()} />)

    expect(screen.getByRole('heading', { name: 'Ordered' })).toBeInTheDocument()
    expect(screen.getByText('ordered')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(pencilOf('purchaseRequestLineStatuses.detail.system_key')).not.toBeInTheDocument()
    expect(pencilOf('purchaseRequestLineStatuses.detail.sort_order')).not.toBeInTheDocument()
  })

  it('offers a pencil on name, color and icon, and no delete button', () => {
    render(<PurchaseRequestLineStatusDetailView purchaseRequestLineStatus={lineStatus()} />)

    expect(pencilOf('purchaseRequestLineStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('purchaseRequestLineStatuses.form.color')).toBeInTheDocument()
    expect(pencilOf('purchaseRequestLineStatuses.form.icon')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /delete/i })).not.toBeInTheDocument()
  })

  it('Save sends only the changed field, never sort_order or system_key', async () => {
    updateMock.mockResolvedValue(lineStatus({ name: 'In purchase' }))
    render(<PurchaseRequestLineStatusDetailView purchaseRequestLineStatus={lineStatus()} />)

    fireEvent.click(pencilOf('purchaseRequestLineStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'In purchase' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(3, { name: 'In purchase' }))
  })

  it('offers no pencil without the update permission', () => {
    const base = lineStatus()
    render(
      <PurchaseRequestLineStatusDetailView
        purchaseRequestLineStatus={lineStatus({
          permissions: { ...base.permissions, resource: { ...base.permissions.resource, update: false } },
        })}
      />,
    )

    expect(pencilOf('purchaseRequestLineStatuses.form.name')).not.toBeInTheDocument()
  })
})
