import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { bulkChangePurchaseOrderStatus } from '@/features/purchase-orders/api'
import type { BulkStatusResult } from '@/features/purchase-orders/purchase-order-bulk-status'
import { usePurchaseOrderBulkStatus } from '@/features/purchase-orders/use-purchase-order-bulk-status'
import type { TableSelection } from '@/features/table/use-bulk-actions-slot'

vi.mock('@/features/purchase-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-orders/api')>()),
  bulkChangePurchaseOrderStatus: vi.fn(),
}))

vi.mock('@/features/purchase-order-statuses/for-select-api', async () => {
  const { PURCHASE_ORDER_STATUS_CATALOG_FIXTURE } = await import('@/features/purchase-order-statuses/purchase-order-status-fixtures')
  return { fetchPurchaseOrderStatusCatalog: async () => PURCHASE_ORDER_STATUS_CATALOG_FIXTURE }
})

const canMock = vi.hoisted(() => vi.fn<(permission: string) => boolean>())
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }))
vi.mock('sonner', () => ({ toast: toastMock }))

const bulkMock = vi.mocked(bulkChangePurchaseOrderStatus)
const refreshGrid = vi.fn()
const clearSelection = vi.fn()

function selectionOf(...ids: number[]): TableSelection {
  return { ids, rows: [] }
}

/** Renders the menu entries the way the grid's Actions dropdown would, plus the dialog slot. */
function Harness({ selection }: { selection: TableSelection }) {
  const { getBulkActions, dialogSlot } = usePurchaseOrderBulkStatus({ refreshGrid, clearSelection })
  const items = getBulkActions?.(selection) ?? []
  return (
    <div>
      {items.map((item) => (
        <button key={item.key} type="button" onClick={item.onSelect}>
          {item.label}
        </button>
      ))}
      {dialogSlot}
    </div>
  )
}

function renderHarness(selection: TableSelection) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness selection={selection} />
    </QueryClientProvider>,
  )
}

const PREVIEW: BulkStatusResult = {
  applied: false,
  to_status: 'in_validation',
  total: 4,
  changed: [
    { id: 1, code: '1/2026' },
    { id: 2, code: '2/2026' },
    { id: 3, code: '3/2026' },
  ],
  skipped: [{ id: 4, code: '4/2026', reason: 'The order is cancelled.' }],
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  bulkMock.mockReset()
  refreshGrid.mockReset()
  clearSelection.mockReset()
  toastMock.success.mockReset()
  toastMock.error.mockReset()
})

describe('ODA bulk status change - menu entry', () => {
  it('is offered with changeStatus or approve and a selection', () => {
    canMock.mockImplementation((permission) => permission === 'purchase-orders.approve')
    renderHarness(selectionOf(1, 2))

    expect(screen.getByRole('button', { name: 'Change status' })).toBeInTheDocument()
  })

  it('is hidden without either permission or without a selection', () => {
    canMock.mockReturnValue(false)
    const { unmount } = renderHarness(selectionOf(1))
    expect(screen.queryByRole('button', { name: 'Change status' })).not.toBeInTheDocument()
    unmount()

    canMock.mockReturnValue(true)
    renderHarness(selectionOf())
    expect(screen.queryByRole('button', { name: 'Change status' })).not.toBeInTheDocument()
  })
})

describe('ODA bulk status change - dialog', () => {
  it('offers the five manual statuses, previews "N of M" with the skipped reasons and applies on confirm', async () => {
    bulkMock.mockResolvedValueOnce(PREVIEW).mockResolvedValueOnce({ ...PREVIEW, applied: true })
    renderHarness(selectionOf(1, 2, 3, 4))

    fireEvent.click(screen.getByRole('button', { name: 'Change status' }))
    const group = await screen.findByRole('radiogroup', { name: 'New status' })
    await waitFor(() => expect(group.querySelectorAll('input[type="radio"]')).toHaveLength(5))
    expect(screen.getByRole('radio', { name: 'Draft' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Cancelled' })).toBeInTheDocument()
    expect(screen.queryByRole('radio', { name: 'Fulfilled' })).not.toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Change status' }).at(-1)).toBeDisabled()

    fireEvent.click(screen.getByRole('radio', { name: 'In validation' }))

    expect(await screen.findByText(/3 of 4 can move to/)).toBeInTheDocument()
    expect(bulkMock).toHaveBeenCalledWith({ ids: [1, 2, 3, 4], to_status: 'in_validation', dry_run: true })
    expect(screen.getByText('4/2026')).toBeInTheDocument()
    expect(screen.getByText(/The order is cancelled\./)).toBeInTheDocument()

    const confirm = screen.getAllByRole('button', { name: 'Change status' }).at(-1)!
    expect(confirm).toBeEnabled()
    fireEvent.click(confirm)

    await waitFor(() =>
      expect(bulkMock).toHaveBeenLastCalledWith({ ids: [1, 2, 3, 4], to_status: 'in_validation', dry_run: false }),
    )
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('3 updated, 1 unchanged.'))
    expect(refreshGrid).toHaveBeenCalledTimes(1)
    expect(clearSelection).toHaveBeenCalledTimes(1)
    await waitFor(() => expect(screen.queryByRole('radiogroup')).not.toBeInTheDocument())
  })

  it('keeps Confirm disabled when no order can move', async () => {
    bulkMock.mockResolvedValueOnce({
      ...PREVIEW,
      changed: [],
      skipped: [{ id: 4, code: '4/2026', reason: 'Already in this status.' }],
      total: 1,
    })
    renderHarness(selectionOf(4))

    fireEvent.click(screen.getByRole('button', { name: 'Change status' }))
    fireEvent.click(await screen.findByRole('radio', { name: 'Draft' }))

    expect(await screen.findByText(/0 of 1 can move to/)).toBeInTheDocument()
    expect(screen.getByText(/Already in this status\./)).toBeInTheDocument()
    const buttons = screen.getAllByRole('button', { name: 'Change status' })
    expect(buttons.at(-1)).toBeDisabled()
  })
})
