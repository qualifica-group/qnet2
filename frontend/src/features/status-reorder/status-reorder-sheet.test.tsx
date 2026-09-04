import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import {
  StatusReorderSheet,
  type StatusReorderSheetLabels,
} from '@/features/status-reorder/status-reorder-sheet'
import type { StatusReorderItem } from '@/features/status-reorder/types'

/**
 * Spec 0039 AC-011. Mirrors `sortable-list.test.tsx`'s jsdom rect/keyboard-
 * sensor workarounds — this suite drives the SAME underlying `<SortableList>`
 * through the real `useStatusReorder` hook, mocking only the HTTP boundary
 * (`@/features/status-reorder/api`) and `sonner`.
 */

const fetchStatusesForReorderMock = vi.fn()
const reorderStatusesMock = vi.fn()

vi.mock('@/features/status-reorder/api', () => ({
  fetchStatusesForReorder: (...args: unknown[]) => fetchStatusesForReorderMock(...args),
  reorderStatuses: (...args: unknown[]) => reorderStatusesMock(...args),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

const ITEMS: StatusReorderItem[] = [
  { id: 1, name: 'New', systemKey: 'new' },
  { id: 2, name: 'Alpha', systemKey: null },
  { id: 3, name: 'Bravo', systemKey: null },
  { id: 4, name: 'Closed', systemKey: 'closed' },
]

/** Opportunity statuses: 3 system rows — "New" leads, "Won"/"Lost" are pinned in the tail, in that order. */
const OPPORTUNITY_ITEMS: StatusReorderItem[] = [
  { id: 1, name: 'New', systemKey: 'new' },
  { id: 2, name: 'Alpha', systemKey: null },
  { id: 3, name: 'Bravo', systemKey: null },
  { id: 4, name: 'Won', systemKey: 'won' },
  { id: 5, name: 'Lost', systemKey: 'lost' },
]

const LABELS = {
  title: 'Reorder statuses',
  subtitle: 'Drag to reorder.',
  dragHandleLabel: 'Drag to reorder',
  loadError: 'Unable to load the statuses.',
  saved: 'Order updated successfully.',
  forbidden: 'You cannot reorder these statuses.',
  genericError: 'Unable to update the order.',
}

const ROW_HEIGHT = 40

/** See `sortable-list.test.tsx`: jsdom's all-zero rects tie every row, so this stubs a rect whose `top` follows DOM order. */
function mockRowRects() {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (
    this: HTMLElement,
  ) {
    const rows = Array.from(document.querySelectorAll('li'))
    const index = rows.indexOf(this as HTMLLIElement)
    const top = index === -1 ? 0 : index * ROW_HEIGHT
    return {
      width: 280,
      height: ROW_HEIGHT,
      top,
      bottom: top + ROW_HEIGHT,
      left: 0,
      right: 280,
      x: 0,
      y: top,
      toJSON: () => ({}),
    } as DOMRect
  })
}

/** The keyboard sensor attaches its move/drop listeners in a macrotask. */
async function flushSensorAttach() {
  await new Promise((resolve) => setTimeout(resolve, 0))
}

function renderSheet(
  onReordered = vi.fn(),
  resource = 'pipeline-statuses',
  // Annotated, not inferred: inferring from LABELS would narrow the type to
  // its own literal shape and reject the optional `inactiveBadge`.
  labels: StatusReorderSheetLabels = LABELS,
) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <StatusReorderSheet
        open
        onOpenChange={vi.fn()}
        resource={resource}
        labels={labels}
        onReordered={onReordered}
      />
    </QueryClientProvider>,
  )
}

/** Drags the first non-pinned handle (Alpha) one position down via keyboard, mirroring `sortable-list.test.tsx`. */
async function dragFirstCustomDown() {
  const [alphaHandle] = screen.getAllByRole('button', { name: LABELS.dragHandleLabel })
  alphaHandle.focus()
  fireEvent.keyDown(alphaHandle, { code: 'Space' })
  await flushSensorAttach()
  fireEvent.keyDown(document, { code: 'ArrowDown' })
  fireEvent.keyDown(document, { code: 'Space' })
}

beforeEach(() => {
  mockRowRects()
  fetchStatusesForReorderMock.mockReset().mockResolvedValue(ITEMS)
  reorderStatusesMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('StatusReorderSheet (spec 0039 AC-011)', () => {
  it('pins New first and Closed last, without a drag handle', async () => {
    renderSheet()

    await screen.findByText('New')
    expect(screen.getAllByRole('button', { name: LABELS.dragHandleLabel })).toHaveLength(2)
    const rows = screen.getAllByRole('listitem')
    expect(rows[0]).toHaveTextContent('New')
    expect(rows[rows.length - 1]).toHaveTextContent('Closed')
  })

  it('persists a drag with only the custom ids, in visual order', async () => {
    reorderStatusesMock.mockResolvedValue([
      { id: 3, sort_order: 10, system_key: null },
      { id: 2, sort_order: 20, system_key: null },
    ])
    const onReordered = vi.fn()
    renderSheet(onReordered)
    await screen.findByText('New')

    await dragFirstCustomDown()

    await waitFor(() => expect(reorderStatusesMock).toHaveBeenCalledWith('pipeline-statuses', [3, 2]))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith(LABELS.saved))
    expect(onReordered).toHaveBeenCalledTimes(1)
  })

  it('reverts the order and shows a toast on a 403', async () => {
    reorderStatusesMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )
    renderSheet()
    await screen.findByText('New')

    await dragFirstCustomDown()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(LABELS.forbidden))
    const rows = screen.getAllByRole('listitem')
    expect(rows.map((row) => row.textContent)).toEqual(['New', 'Alpha', 'Bravo', 'Closed'])
  })

  it('reverts the order and shows a generic toast on a 422', async () => {
    reorderStatusesMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Invalid ids' },
      } as never),
    )
    renderSheet()
    await screen.findByText('New')

    await dragFirstCustomDown()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(LABELS.genericError))
    const rows = screen.getAllByRole('listitem')
    expect(rows.map((row) => row.textContent)).toEqual(['New', 'Alpha', 'Bravo', 'Closed'])
  })
})

/**
 * Payment methods (spec 0068): no system row at all, mirroring the real
 * shape `use-status-reorder.ts` sees for this resource — every entry is an
 * ordinary custom row from the start.
 */
const PAYMENT_METHOD_ITEMS: StatusReorderItem[] = [
  { id: 1, name: 'Bank transfer', systemKey: null },
  { id: 2, name: 'Cash', systemKey: null },
  { id: 3, name: 'Card', systemKey: null },
  { id: 4, name: 'Check', systemKey: null },
]

describe('StatusReorderSheet — D-5 hardening (AC-120)', () => {
  /**
   * The MECHANICAL regression guard for the `?? null` fallback lives in
   * `reconcile-reordered-items.test.ts` — a direct unit test of the
   * extracted pure mapping, verified to go red when the fallback is removed
   * and green with it restored (see that file's doc for the criterion).
   *
   * THIS suite cannot serve as that guard: `useStatusReorder` never
   * invalidates/rewrites its own `['status-reorder', resource, 'list']`
   * query after a successful reorder (spec 0068 §scope.out, a separate,
   * documented, PRE-EXISTING, OUT-OF-SCOPE issue). Confirmed by direct
   * observation while building this test (temporarily asserting
   * post-success row text on the already-green "persists a drag…" test
   * above): `syncedFrom` diverges from the still-stale `listQuery.data` the
   * instant the `.then()` handler applies the reconciled response, and the
   * render-time resync guard (`if (listQuery.data !== syncedFrom) …`)
   * discards that render and reapplies `listQuery.data` BEFORE it ever
   * commits — so the reconciled response (hardened or not) is never
   * actually painted; the sheet's visible post-success state is always the
   * pre-drag list. A DOM assertion here therefore cannot discriminate
   * hardened from unhardened code either way.
   *
   * What THIS test still verifies, honestly: the full Sheet integration
   * does not break/crash when the backend response omits `system_key`, and
   * a `payment-methods`-shaped resource (no system rows, this resource's
   * real shape) stays fully usable — a drag, a successful save, and a
   * second drag sending the complete id set. A fixture WITH pinned rows
   * would only ever show those pinned rows post-success (the stale,
   * reverted-to list), regardless of the fix, which is why a no-system-row
   * fixture is used instead of one that would look like it demonstrates the
   * fallback but actually wouldn't.
   */
  it('keeps every row draggable and sends the full id set on the next drag when the response omits system_key', async () => {
    fetchStatusesForReorderMock.mockResolvedValue(PAYMENT_METHOD_ITEMS)
    // No `system_key` key at all on any entry — the regression scenario the
    // hardening guards against.
    reorderStatusesMock.mockResolvedValueOnce([
      { id: 2, sort_order: 10 },
      { id: 3, sort_order: 20 },
      { id: 1, sort_order: 30 },
      { id: 4, sort_order: 40 },
    ])
    renderSheet(vi.fn(), 'payment-methods')
    await screen.findByText('Bank transfer')
    expect(screen.getAllByRole('button', { name: LABELS.dragHandleLabel })).toHaveLength(4)

    await dragFirstCustomDown()

    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith(LABELS.saved))
    expect(screen.getAllByRole('button', { name: LABELS.dragHandleLabel })).toHaveLength(4)

    reorderStatusesMock.mockResolvedValueOnce([])
    const [firstHandle] = screen.getAllByRole('button', { name: LABELS.dragHandleLabel })
    firstHandle.focus()
    fireEvent.keyDown(firstHandle, { code: 'Space' })
    await flushSensorAttach()
    fireEvent.keyDown(document, { code: 'ArrowDown' })
    fireEvent.keyDown(document, { code: 'Space' })

    await waitFor(() => expect(reorderStatusesMock).toHaveBeenCalledTimes(2))
    const secondCallIds = reorderStatusesMock.mock.calls[1][1] as number[]
    expect([...secondCallIds].sort((a, b) => a - b)).toEqual([1, 2, 3, 4])
  })
})

describe('StatusReorderSheet — opportunity statuses, 3 system rows', () => {
  it('pins New first and Won/Lost last in that order, leaving only the custom rows draggable', async () => {
    fetchStatusesForReorderMock.mockResolvedValue(OPPORTUNITY_ITEMS)

    renderSheet()

    await screen.findByText('New')
    expect(screen.getAllByRole('button', { name: LABELS.dragHandleLabel })).toHaveLength(2)
    const rows = screen.getAllByRole('listitem')
    expect(rows.map((row) => row.textContent)).toEqual(['New', 'Alpha', 'Bravo', 'Won', 'Lost'])
  })
})

/**
 * Spec 0101: the sheet lists DEACTIVATED rows too, because the reorder
 * endpoints validate `ordered_ids` against the full set. A badge explains why
 * a row absent from every picker shows up here. The marker is driven by an
 * explicit `isActive === false`, never by falsiness — the modules whose
 * for-select projects no `is_active` leave it `undefined` and must render
 * nothing at all.
 */
const INACTIVE_BADGE = 'Inactive'

/** Task priorities: pure lookup, no system row, one row deactivated. */
const LOOKUP_ITEMS: StatusReorderItem[] = [
  { id: 1, name: 'High', systemKey: null, isActive: true },
  { id: 2, name: 'Obsolete', systemKey: null, isActive: false },
]

describe('StatusReorderSheet — inactive marker (spec 0101)', () => {
  it('badges only the deactivated row, and lists it as draggable', async () => {
    fetchStatusesForReorderMock.mockResolvedValue(LOOKUP_ITEMS)

    renderSheet(vi.fn(), 'task-priorities', { ...LABELS, inactiveBadge: INACTIVE_BADGE })

    await screen.findByText('Obsolete')
    expect(screen.getAllByText(INACTIVE_BADGE)).toHaveLength(1)
    // Deactivated does NOT mean pinned: the backend demands it in `ordered_ids`.
    expect(screen.getAllByRole('button', { name: LABELS.dragHandleLabel })).toHaveLength(2)
  })

  it('renders no badge for a resource that projects no is_active (the modules in production)', async () => {
    // ITEMS carries no `isActive` at all: pipeline/contract/reward statuses
    // and payment methods must be visually untouched by this change.
    renderSheet(vi.fn(), 'pipeline-statuses', { ...LABELS, inactiveBadge: INACTIVE_BADGE })

    await screen.findByText('New')
    expect(screen.queryByText(INACTIVE_BADGE)).not.toBeInTheDocument()
  })

  it('renders no badge when the caller supplies no label, even on a deactivated row', async () => {
    fetchStatusesForReorderMock.mockResolvedValue(LOOKUP_ITEMS)

    renderSheet(vi.fn(), 'task-priorities')

    await screen.findByText('Obsolete')
    expect(screen.queryByText(INACTIVE_BADGE)).not.toBeInTheDocument()
  })
})
