import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { RewardDetailRenderer } from '@/features/rewarded-referents/reward-detail-renderer'
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

const REWARD: RewardDetailItem = {
  id: 10,
  assigned_at: '2026-06-01',
  notes: 'Consegnato a mano.',
  reward_type: { id: 1, name: 'Amazon voucher', color: 'blue' },
  source: { type: 'opportunity', id: 42, name: 'Fornitura uffici', path: '/opportunities/42' },
  context: {
    registry: { id: 5, name: 'Acme S.p.A.' },
    product_categories: [{ id: 1, name: 'Elettronica' }],
    opportunity_status: { id: 2, name: 'In corso', color: 'blue', group: 'open' },
    workflow_status: { id: 3, name: 'In lavorazione', color: 'amber' },
    operator: { id: 7, name: 'Mario Rossi', avatar_url: null },
  },
}

const ROW: TableRow = { id: 1, actions: [] }

function renderDetail(client: QueryClient) {
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <RewardDetailRenderer {...({ data: ROW } as ICellRendererParams<TableRow>)} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchReferentRewardsMock.mockReset()
})

describe('RewardDetailRenderer — lazy load and caching (AC-026)', () => {
  it('fetches once, shows a loading state, then renders a card per reward', async () => {
    fetchReferentRewardsMock.mockResolvedValue([REWARD])
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    renderDetail(client)

    await waitFor(() => expect(screen.getByText('Amazon voucher')).toBeInTheDocument())

    expect(fetchReferentRewardsMock).toHaveBeenCalledTimes(1)
    expect(fetchReferentRewardsMock).toHaveBeenCalledWith(1)

    const link = screen.getByRole('link', { name: /Fornitura uffici/ })
    expect(link).toHaveAttribute('href', '/opportunities/42')
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
