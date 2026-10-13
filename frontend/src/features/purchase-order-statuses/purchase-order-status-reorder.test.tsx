import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StatusReorderSheet } from '@/features/status-reorder/status-reorder-sheet'
import {
  PURCHASE_ORDER_STATUS_CATALOG_FIXTURE,
  PURCHASE_ORDER_STATUS_FOR_SELECT_ITEMS,
} from '@/features/purchase-order-statuses/purchase-order-status-fixtures'
import { PURCHASE_ORDER_STATUSES_RESOURCE } from '@/features/purchase-order-statuses/types'

/**
 * Spec 0248 D-6 / AC-010: every ODA status row carries a `system_key` (its enum
 * value), yet none is pinned in the reorder sheet and `ordered_ids` carries all
 * of them. Only the HTTP boundary is mocked; the real hook and sheet run.
 */

const reorderStatusesMock = vi.fn()

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(async () => ({
    items: PURCHASE_ORDER_STATUS_FOR_SELECT_ITEMS,
    export_link: null,
    pagination: { total: 7, offset: 0, limit: 100, total_pages: 1 },
  })),
}))

vi.mock('@/features/status-reorder/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/status-reorder/api')>()),
  reorderStatuses: (...args: unknown[]) => reorderStatusesMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const LABELS = {
  title: 'Reorder ODA statuses',
  subtitle: 'Drag the rows.',
  dragHandleLabel: 'Drag to reorder',
  loadError: 'Unable to load.',
  saved: 'Saved.',
  forbidden: 'Forbidden.',
  genericError: 'Error.',
}

const ROW_HEIGHT = 40

/** jsdom rects are all zero and tie every row: stub a rect whose `top` follows DOM order. */
function mockRowRects() {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (this: HTMLElement) {
    const index = Array.from(document.querySelectorAll('li')).indexOf(this as HTMLLIElement)
    const top = index === -1 ? 0 : index * ROW_HEIGHT
    return { width: 280, height: ROW_HEIGHT, top, bottom: top + ROW_HEIGHT, left: 0, right: 280, x: 0, y: top, toJSON: () => ({}) } as DOMRect
  })
}

function renderSheet() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <StatusReorderSheet
        open
        onOpenChange={vi.fn()}
        resource={PURCHASE_ORDER_STATUSES_RESOURCE}
        labels={LABELS}
        onReordered={vi.fn()}
        allRowsReorderable
      />
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  mockRowRects()
  reorderStatusesMock.mockReset()
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('ODA status reorder sheet', () => {
  it('lists every status with its own drag handle: no row is pinned', async () => {
    renderSheet()

    await screen.findByText('Draft')
    expect(screen.getAllByRole('button', { name: LABELS.dragHandleLabel })).toHaveLength(PURCHASE_ORDER_STATUS_CATALOG_FIXTURE.length)
  })

  it('persists a drag with all the ids in the new visual order', async () => {
    reorderStatusesMock.mockResolvedValue(
      [2, 1, 3, 4, 5, 6, 7].map((id, index) => ({ id, sort_order: index, system_key: 'draft' })),
    )
    renderSheet()
    await screen.findByText('Draft')

    const [firstHandle] = screen.getAllByRole('button', { name: LABELS.dragHandleLabel })
    firstHandle.focus()
    fireEvent.keyDown(firstHandle, { code: 'Space' })
    await new Promise((resolve) => setTimeout(resolve, 0))
    fireEvent.keyDown(document, { code: 'ArrowDown' })
    fireEvent.keyDown(document, { code: 'Space' })

    await waitFor(() => expect(reorderStatusesMock).toHaveBeenCalledWith(PURCHASE_ORDER_STATUSES_RESOURCE, [2, 1, 3, 4, 5, 6, 7]))
    // A reorder response carrying `system_key` must not pin the rows afterwards.
    await waitFor(() =>
      expect(screen.getAllByRole('button', { name: LABELS.dragHandleLabel })).toHaveLength(PURCHASE_ORDER_STATUS_CATALOG_FIXTURE.length),
    )
  })
})
