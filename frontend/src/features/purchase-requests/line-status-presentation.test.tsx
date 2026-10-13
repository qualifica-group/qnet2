import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import {
  LINE_STATUS_CATALOG_FIXTURE,
  seedLineStatusCatalog,
} from '@/features/purchase-request-line-statuses/line-status-catalog-fixtures'
import type { LineStatusPresentation } from '@/features/purchase-request-line-statuses/use-purchase-request-line-status-catalog'
import { LineStatusHistoryDialog } from '@/features/purchase-requests/line-status-history-dialog'
import { LineStatusOptions } from '@/features/purchase-requests/line-status-options'
import { LineStatusSummaryTable } from '@/features/purchase-requests/line-status-summary-table'
import { LineStatusTabs } from '@/features/purchase-requests/line-status-tabs'
import { LineStatusCountsCell } from '@/features/purchase-requests/purchase-request-cells'
import { LineStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'
import type { LineTarget } from '@/features/purchase-requests/line-status-transitions'

/**
 * Spec 0248 AC-009: badge, tabs, target options, history, recap and counts
 * draw the configured name/color/icon of the catalog, in the catalog order.
 * The catalog is seeded in the query cache (no request, no wiring mock).
 */

vi.mock('@/features/purchase-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-requests/api')>()),
  fetchLineStatusLogs: vi.fn(async () => [
    {
      id: 1,
      from_status: 'approved',
      to_status: 'ordered',
      reason: null,
      is_bulk: false,
      created_at: '2026-10-01T10:00:00Z',
      user: { id: 1, name: 'Mario Rossi' },
    },
  ]),
}))

/** Renamed and recolored defaults, with `ordered` moved first. */
const CUSTOM_CATALOG: readonly LineStatusPresentation[] = [
  { ...LINE_STATUS_CATALOG_FIXTURE[2], name: 'In purchase', color: 'violet', sortOrder: 1 },
  { ...LINE_STATUS_CATALOG_FIXTURE[0], name: 'Waiting', sortOrder: 2 },
  ...LINE_STATUS_CATALOG_FIXTURE.slice(1, 2).map((status) => ({ ...status, sortOrder: 3 })),
  ...LINE_STATUS_CATALOG_FIXTURE.slice(3).map((status, index) => ({ ...status, sortOrder: 4 + index })),
]

function renderSeeded(ui: React.ReactElement, catalog?: readonly LineStatusPresentation[]) {
  const client = seedLineStatusCatalog(new QueryClient({ defaultOptions: { queries: { retry: false } } }), catalog)
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

function lineTarget(overrides: Partial<LineTarget> = {}): LineTarget {
  return {
    id: 7,
    status: 'pending_approval',
    transitions: [],
    capabilities: [],
    description: 'Laptop',
    quantity: 1,
    unitSymbol: 'pc',
    totalAmount: 10,
    supplier: null,
    canUpdateSupplier: false,
    purchaseOrder: null,
    purchaseRequestId: 5,
    purchaseRequestSubject: 'Laptops',
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('line status presentation from the catalog (spec 0248 AC-009)', () => {
  it('draws the badge with the configured name, color and icon', () => {
    renderSeeded(<LineStatusBadge status="ordered" />, CUSTOM_CATALOG)

    const badge = screen.getByText('In purchase').closest('[data-slot="badge"]')
    expect(badge?.className).toMatch(/violet/)
    expect(badge?.querySelector('svg')).not.toBeNull()
    expect(screen.queryByText('Ordered')).not.toBeInTheDocument()
  })

  it('falls back to the raw value on a neutral badge when the catalog does not know the status', () => {
    renderSeeded(<LineStatusBadge status="ordered" />, [])

    expect(screen.getByText('ordered')).toBeInTheDocument()
  })

  it('lists the tabs after "All" in the catalog order with the configured names', () => {
    renderSeeded(<LineStatusTabs active="all" onSelect={vi.fn()} label="Status" />, CUSTOM_CATALOG)

    const names = screen.getAllByRole('tab').map((tab) => tab.textContent)
    expect(names).toEqual(['All', 'In purchase', 'Waiting', 'Approved', 'Received', 'Rejected', 'On hold'])
  })

  it('selects the enum value, not the name, when a tab is pressed', () => {
    const onSelect = vi.fn()
    renderSeeded(<LineStatusTabs active="all" onSelect={onSelect} label="Status" />, CUSTOM_CATALOG)

    fireEvent.click(screen.getByRole('tab', { name: 'In purchase' }))

    expect(onSelect).toHaveBeenCalledWith('ordered')
  })

  it('offers the target options in the catalog order with names and tags the current one', () => {
    renderSeeded(
      <>
        <span id="label">Status</span>
        <LineStatusOptions
          labelledBy="label"
          options={['pending_approval', 'approved', 'ordered']}
          value={null}
          current="pending_approval"
          onValueChange={vi.fn()}
        />
      </>,
      CUSTOM_CATALOG,
    )

    const labels = within(screen.getByRole('radiogroup')).getAllByRole('radio').map((radio) => radio.parentElement?.textContent)
    expect(labels).toEqual(['In purchase', 'Waiting (current)', 'Approved'])
  })

  it('shows the configured names in the history of a line', async () => {
    renderSeeded(<LineStatusHistoryDialog lineId={7} onOpenChange={vi.fn()} />, CUSTOM_CATALOG)

    expect(await screen.findByText('In purchase')).toBeInTheDocument()
    expect(screen.getByText('Approved')).toBeInTheDocument()
  })

  it('shows the configured name as the current status in the recap table', () => {
    renderSeeded(<LineStatusSummaryTable targets={[lineTarget()]} />, CUSTOM_CATALOG)

    expect(screen.getByText('Waiting')).toBeInTheDocument()
  })

  it('shows per-status counts as configured names, skipping zero counts', () => {
    renderSeeded(
      <LineStatusCountsCell {...({ value: { ordered: 2, approved: 1, received: 0 } } as unknown as ICellRendererParams)} />,
      CUSTOM_CATALOG,
    )

    expect(screen.getByText('In purchase 2')).toBeInTheDocument()
    expect(screen.getByText('Approved 1')).toBeInTheDocument()
    expect(screen.queryByText(/Received/)).not.toBeInTheDocument()
  })
})
