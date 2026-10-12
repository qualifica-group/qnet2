import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { LineSupplierDialog } from '@/features/purchase-requests/line-supplier-dialog'
import type { LineTarget } from '@/features/purchase-requests/line-status-transitions'
import type { LineSupplierPayload, LineSupplierResult } from '@/features/purchase-requests/types'

const assignMock = vi.fn<(payload: LineSupplierPayload) => Promise<LineSupplierResult>>()
vi.mock('@/features/purchase-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-requests/api')>()),
  assignLinesSupplier: (payload: LineSupplierPayload) => assignMock(payload),
}))

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(async () => ({
    items: [{ id: 12, label: 'Rossi Srl' }],
    export_link: null,
    pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 },
  })),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() } }))

function target(overrides: Partial<LineTarget> & Pick<LineTarget, 'id'>): LineTarget {
  return {
    status: 'approved',
    transitions: [],
    capabilities: ['fulfill'],
    description: `Line ${overrides.id}`,
    quantity: 1,
    unitSymbol: 'pc',
    totalAmount: 10,
    supplier: null,
    canUpdateSupplier: true,
    purchaseRequestId: 5,
    purchaseRequestSubject: 'Laptops',
    ...overrides,
  }
}

function renderDialog(targets: LineTarget[], onAssigned = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <LineSupplierDialog targets={targets} onOpenChange={vi.fn()} onAssigned={onAssigned} />
    </QueryClientProvider>,
  )
  return onAssigned
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  assignMock.mockReset().mockResolvedValue({ updated_count: 2 })
})

describe('LineSupplierDialog (spec 0209 AC-016)', () => {
  it('sends line_ids and supplier_id once a supplier is chosen, then reports the result', async () => {
    const onAssigned = renderDialog([target({ id: 1 }), target({ id: 2 })])
    const confirm = screen.getByRole('button', { name: 'Assign to 2 lines' })
    expect(confirm).toBeDisabled()

    fireEvent.click(screen.getByRole('combobox', { name: 'Supplier' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Rossi Srl' }))
    await waitFor(() => expect(confirm).toBeEnabled())
    fireEvent.click(confirm)

    await waitFor(() => expect(assignMock).toHaveBeenCalledWith({ line_ids: [1, 2], supplier_id: 12 }))
    await waitFor(() => expect(onAssigned).toHaveBeenCalledWith({ updated_count: 2 }))
  })

  it('shows the message and cannot confirm when a line can no longer change its supplier', () => {
    renderDialog([target({ id: 1 }), target({ id: 2, status: 'ordered', canUpdateSupplier: false })])

    expect(screen.getByRole('alert')).toHaveTextContent('The supplier of 1 line(s) can no longer be changed')
    expect(screen.getByRole('button', { name: 'Assign to 2 lines' })).toBeDisabled()
    expect(assignMock).not.toHaveBeenCalled()
  })
})
