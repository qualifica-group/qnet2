import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { RewardDetailRenderer } from '@/features/rewarded-referents/reward-detail-renderer'
import { apiClient } from '@/api/client'
import type { RewardDetailItem } from '@/features/rewards/types'
import type { TableRow } from '@/features/table/types'

/**
 * Spec 0059 AC-026/AC-027/AC-028: the master/detail lazy panel. Only
 * `fetchReferentRewards` (the network boundary) is mocked; `useReferentRewards`
 * runs for real against a QueryClient instantiated once per test (not per
 * render — a per-render client would reset the cache and defeat the very
 * caching behavior AC-026 asserts).
 */

const fetchReferentRewardsMock = vi.fn<(referentId: number) => Promise<RewardDetailItem[]>>()

vi.mock('@/features/rewarded-referents/api', () => ({
  fetchReferentRewards: (...args: [number]) => fetchReferentRewardsMock(...args),
}))

// Spec 0060 D-8: the card's inline status edit is gated on `rewarded-referents.update`.
const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

// The status select's own network/pagination behavior is `AsyncPaginatedSelect`'s
// responsibility (its own test file); here only the wiring into the PATCH
// mutation is under test, mirroring `reward-card.test.tsx`.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    onChange,
    labels,
  }: {
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button type="button" role="combobox" aria-label={labels.triggerLabel} onClick={() => onChange(5)}>
      pick
    </button>
  ),
}))

// The PATCH itself: only the network boundary is mocked, the real
// `useUpdateRewardStatus` mutation and invalidation run for real.
vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
}))

// The open-mode resolution (modal Sheet vs page navigation) lives in
// `useModuleOpener` and is covered by its own tests; here we only assert the
// panel wires each linked record's id into the opener of ITS OWN module, so
// the hook is mocked (no AuthProvider / module registry needed). The domain is
// recorded alongside the row: an Offerta reference must never open the
// Opportunita' module, and vice versa.
const openViewMock = vi.fn<(domain: string, row: TableRow) => void>()

vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: (domain: string) => ({
    openView: (row: TableRow) => openViewMock(domain, row),
    openCreate: vi.fn(),
    openCreateWith: vi.fn(),
    openEdit: vi.fn(),
    openDuplicate: vi.fn(),
    sheet: null,
  }),
}))

const REWARD: RewardDetailItem = {
  id: 10,
  assigned_at: '2026-06-01',
  notes: 'Consegnato a mano.',
  reward_type: { id: 1, name: 'Amazon voucher', color: 'blue' },
  source: { type: 'opportunity', id: 42, name: 'Fornitura uffici', path: '/opportunities/42' },
  related: [],
  context: {
    registry: { id: 5, name: 'Acme S.p.A.' },
    product_categories: [{ id: 1, name: 'Elettronica' }],
    status: { source: 'quotes', distinct_count: 1, entries: [{ id: 1, name: 'In corso', color: 'blue', group: 'open', count: 1 }] },
    workflow_status: { id: 3, name: 'In lavorazione', color: 'amber' },
    operator: { id: 7, name: 'Mario Rossi', avatar_url: null },
  },
  reward_status: { id: 1, name: 'In attesa', color: 'amber' },
}

/**
 * The same buono born on the OFFERTA instead (user directive 2026-08-31):
 * `context.status` then carries the PARENT opportunity's computed status, so
 * the card captions it "Opportunity status", and `related` carries the
 * opportunity itself.
 */
const QUOTE_ORIGIN_REWARD: RewardDetailItem = {
  ...REWARD,
  id: 11,
  source: { type: 'quote', id: 7, name: 'QUO-0007', path: '/quotes/7' },
  related: [{ type: 'opportunity', id: 42, name: 'Fornitura uffici', path: '/opportunities/42' }],
}

const ROW: TableRow = { id: 1, actions: [] }

function renderDetail(client: QueryClient, params?: Partial<ICellRendererParams<TableRow>>) {
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <RewardDetailRenderer {...({ data: ROW, ...params } as ICellRendererParams<TableRow>)} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchReferentRewardsMock.mockReset()
  openViewMock.mockReset()
  canMock.mockReset()
  canMock.mockReturnValue(false)
  vi.mocked(apiClient.patch).mockReset()
})

describe('RewardDetailRenderer — lazy load and caching (AC-026)', () => {
  it('fetches once, shows a loading state, then renders a card per reward', async () => {
    fetchReferentRewardsMock.mockResolvedValue([REWARD])
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    renderDetail(client)

    await waitFor(() => expect(screen.getByText('Amazon voucher')).toBeInTheDocument())

    expect(fetchReferentRewardsMock).toHaveBeenCalledTimes(1)
    expect(fetchReferentRewardsMock).toHaveBeenCalledWith(1)

    // The origin opens via the module opener (open-mode aware), not a raw link.
    const sourceButton = screen.getByRole('button', { name: /Fornitura uffici/ })
    fireEvent.click(sourceButton)
    expect(openViewMock).toHaveBeenCalledWith('opportunities', { id: 42 })
    expect(screen.getByText('In corso')).toBeInTheDocument()
    expect(screen.getByText('In lavorazione')).toBeInTheDocument()
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
    expect(screen.getByText('Consegnato a mano.')).toBeInTheDocument()
  })

  it('does not refetch when the row collapses and re-expands (same query client)', async () => {
    fetchReferentRewardsMock.mockResolvedValue([REWARD])
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    const first = renderDetail(client)
    await waitFor(() => expect(screen.getByText('Amazon voucher')).toBeInTheDocument())
    first.unmount()

    renderDetail(client)
    await waitFor(() => expect(screen.getByText('Amazon voucher')).toBeInTheDocument())

    expect(fetchReferentRewardsMock).toHaveBeenCalledTimes(1)
  })
})

describe('RewardDetailRenderer — error state with retry (AC-027)', () => {
  it('shows an error message and a retry action instead of an infinite spinner', async () => {
    fetchReferentRewardsMock.mockRejectedValueOnce(new Error('network down'))
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    renderDetail(client)

    await waitFor(() =>
      expect(
        screen.getByText("Unable to load this referent's rewards. Please try again."),
      ).toBeInTheDocument(),
    )

    fetchReferentRewardsMock.mockResolvedValueOnce([REWARD])
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    await waitFor(() => expect(screen.getByText('Amazon voucher')).toBeInTheDocument())
    expect(fetchReferentRewardsMock).toHaveBeenCalledTimes(2)
  })
})

describe('RewardDetailRenderer — empty state (AC-028)', () => {
  it('shows the translated empty state when the referent has no rewards left', async () => {
    fetchReferentRewardsMock.mockResolvedValue([])
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    renderDetail(client)

    await waitFor(() =>
      expect(screen.getByText('No rewards found for this referent.')).toBeInTheDocument(),
    )
  })
})

describe('RewardDetailRenderer — auto-height re-measure on lazy load', () => {
  it('pushes the loaded content height back to the grid so the first expand is not clipped', async () => {
    // A controllable ResizeObserver: fires its callback the moment it starts
    // observing, mirroring the browser's initial delivery.
    let observed = false
    class FakeResizeObserver {
      cb: () => void
      constructor(cb: () => void) {
        this.cb = cb
      }
      observe() {
        observed = true
        this.cb()
      }
      disconnect() {}
      unobserve() {}
    }
    const original = globalThis.ResizeObserver
    globalThis.ResizeObserver = FakeResizeObserver as unknown as typeof ResizeObserver

    try {
      fetchReferentRewardsMock.mockResolvedValue([REWARD])
      const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
      const setRowHeight = vi.fn()
      const onRowHeightChanged = vi.fn()

      renderDetail(client, {
        node: { setRowHeight } as never,
        api: { onRowHeightChanged } as never,
      })

      await waitFor(() => expect(screen.getByText('Amazon voucher')).toBeInTheDocument())

      // The observer only attaches once the cards are on screen (not for the
      // skeleton), and drives the grid re-measure.
      expect(observed).toBe(true)
      expect(setRowHeight).toHaveBeenCalled()
      expect(onRowHeightChanged).toHaveBeenCalled()
    } finally {
      globalThis.ResizeObserver = original
    }
  })
})

describe('RewardDetailRenderer — inline status edit (spec 0060 AC-029/AC-030)', () => {
  it('shows the status as a readonly badge, no select, without rewarded-referents.update', async () => {
    canMock.mockReturnValue(false)
    fetchReferentRewardsMock.mockResolvedValue([REWARD])
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    renderDetail(client)

    await waitFor(() => expect(screen.getByText('Amazon voucher')).toBeInTheDocument())
    expect(screen.getByText('In attesa')).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(apiClient.patch).not.toHaveBeenCalled()
  })

  it('sends the PATCH with the picked reward_status_id and invalidates the list on success', async () => {
    canMock.mockReturnValue(true)
    fetchReferentRewardsMock.mockResolvedValue([REWARD])
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: {
        success: true,
        message: 'ok',
        data: { ...REWARD, reward_status: { id: 5, name: 'Approvato', color: 'green' } },
      },
    })
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    renderDetail(client)

    await waitFor(() => expect(screen.getByText('Amazon voucher')).toBeInTheDocument())
    fireEvent.click(screen.getByRole('combobox', { name: 'Status' }))

    await waitFor(() =>
      expect(apiClient.patch).toHaveBeenCalledWith('/rewards/10', { reward_status_id: 5 }),
    )
    // Invalidation refetches the still-mounted, active query (spec 0060 AC-029).
    await waitFor(() => expect(fetchReferentRewardsMock).toHaveBeenCalledTimes(2))
  })

  /**
   * User report 2026-08-31: "quando cambio stato deve reinderizzare anche
   * sulla tabella, ora devo ricaricare la pagina". The MASTER row's counters
   * are computed server-side from this very status, and SSRM rows live in AG
   * Grid's store, not in React Query — invalidating the detail query alone
   * cannot reach them.
   */
  it('refreshes the SSRM rows so the master counters catch up, without purging the open panel', async () => {
    canMock.mockReturnValue(true)
    fetchReferentRewardsMock.mockResolvedValue([REWARD])
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: {
        success: true,
        message: 'ok',
        data: { ...REWARD, reward_status: { id: 5, name: 'Approvato', color: 'green' } },
      },
    })
    const refreshServerSide = vi.fn()
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    renderDetail(client, { api: { refreshServerSide } as unknown as ICellRendererParams<TableRow>['api'] })

    await waitFor(() => expect(screen.getByText('Amazon voucher')).toBeInTheDocument())
    fireEvent.click(screen.getByRole('combobox', { name: 'Status' }))

    await waitFor(() => expect(refreshServerSide).toHaveBeenCalledWith({ purge: false }))
  })
})

/**
 * User directive 2026-08-31: "Anche nella pagina dedicata ai buoni voglio che
 * ci sia il riferimento all'offerta e non solo in opportunita'".
 */
describe('RewardDetailRenderer — Offerta origin', () => {
  it('opens each linked record in its OWN module and captions the computed status as the opportunity one', async () => {
    fetchReferentRewardsMock.mockResolvedValue([QUOTE_ORIGIN_REWARD])
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    renderDetail(client)

    await waitFor(() => expect(screen.getByText('QUO-0007')).toBeInTheDocument())

    fireEvent.click(screen.getByRole('button', { name: /QUO-0007/ }))
    expect(openViewMock).toHaveBeenCalledWith('quotes', { id: 7 })

    fireEvent.click(screen.getByRole('button', { name: /Fornitura uffici/ }))
    expect(openViewMock).toHaveBeenCalledWith('opportunities', { id: 42 })

    expect(screen.getByText('Opportunity status')).toBeInTheDocument()
    expect(screen.queryByText('Commercial status')).not.toBeInTheDocument()
    // The offer's own working state keeps its label (spec 0083).
    expect(screen.getByText('Workflow status')).toBeInTheDocument()
  })
})
