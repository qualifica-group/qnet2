import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchaseOrderStatusesTable } from '@/features/purchase-order-statuses/purchase-order-statuses-table'

/**
 * Spec 0248 AC-010: the adapter mounts `<TableView domain="purchase-order-statuses">`,
 * gates the reorder button on `purchase-order-statuses.update` and offers
 * neither New nor Delete. `<TableView>` and the page chrome are stubbed: they
 * are framework pieces, outside what this adapter owns.
 */

const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string }>(function TableViewStub({ domain }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return <div role="region" aria-label={`table-${domain}`} />
  }),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <PurchaseOrderStatusesTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('PurchaseOrderStatusesTable', () => {
  it('mounts <TableView domain="purchase-order-statuses">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-purchase-order-statuses' })).toBeInTheDocument()
  })

  it('shows the reorder button with purchase-order-statuses.update', () => {
    canMock.mockImplementation((permission) => permission === 'purchase-order-statuses.update')

    renderTable()

    expect(screen.getByRole('button', { name: i18n.t('purchaseOrderStatuses.reorder.openButton') })).toBeInTheDocument()
  })

  it('hides the reorder button without the permission', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: i18n.t('purchaseOrderStatuses.reorder.openButton') })).not.toBeInTheDocument()
  })

  it('offers no New or Delete button, even with every permission', () => {
    renderTable()

    expect(screen.getAllByRole('button')).toHaveLength(1)
    expect(screen.queryByRole('button', { name: /new|delete/i })).not.toBeInTheDocument()
  })
})
