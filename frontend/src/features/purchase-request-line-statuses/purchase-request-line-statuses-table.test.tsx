import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchaseRequestLineStatusesTable } from '@/features/purchase-request-line-statuses/purchase-request-line-statuses-table'

/**
 * Spec 0248 AC-010: the list has the reorder toggle (gated on `.update`) and
 * neither a "New" nor a delete affordance. The grid and the app chrome are
 * stubbed: this suite asserts the adapter wiring.
 */

const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

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
        <PurchaseRequestLineStatusesTable />
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

describe('PurchaseRequestLineStatusesTable (spec 0248 AC-010)', () => {
  it('mounts the generic table on the purchase-request-line-statuses domain', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-purchase-request-line-statuses' })).toBeInTheDocument()
  })

  it('shows the reorder button with purchase-request-line-statuses.update', () => {
    renderTable()

    expect(screen.getByRole('button', { name: i18n.t('purchaseRequestLineStatuses.reorder.openButton') })).toBeInTheDocument()
  })

  it('hides the reorder button without the update permission', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: i18n.t('purchaseRequestLineStatuses.reorder.openButton') })).not.toBeInTheDocument()
  })

  it('offers no New and no Delete button even with every permission', () => {
    renderTable()

    expect(screen.queryByRole('button', { name: /new|delete/i })).not.toBeInTheDocument()
  })
})
