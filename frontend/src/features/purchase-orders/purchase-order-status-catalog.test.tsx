import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import type { PurchaseOrderStatusCatalogEntry } from '@/features/purchase-order-statuses/for-select-api'
import { PurchaseOrderStatusBadge } from '@/features/purchase-orders/purchase-order-status-badge'
import { PurchaseOrderStatusOptions } from '@/features/purchase-orders/purchase-order-status-options'
import { StatusTabs } from '@/features/purchase-orders/status-tabs'

// The pickers render the quick-create "+" (spec 0028), gated by `<Can>`: no abilities here, so it stays hidden.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

/**
 * Spec 0248 AC-008: badge, status tabs and the target options of the status
 * dialog draw name, color, icon and order from the catalog, not from maps in
 * the code. The catalog below is DELIBERATELY renamed, recolored and reordered
 * against the seeded one: an app still reading cabled data would not match.
 */

const CATALOG: PurchaseOrderStatusCatalogEntry[] = [
  { id: 7, systemKey: 'cancelled', name: 'Dropped', color: 'violet', icon: 'flag' },
  { id: 1, systemKey: 'draft', name: 'Sketch', color: 'pink', icon: 'pen-line' },
  { id: 3, systemKey: 'approved', name: 'Green light', color: 'green', icon: null },
]

vi.mock('@/features/purchase-order-statuses/for-select-api', () => ({
  fetchPurchaseOrderStatusCatalog: async () => CATALOG,
}))

/** One QueryClient per test, shared by every render of that test. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('PurchaseOrderStatusBadge', () => {
  it('shows the catalog name, color and icon', async () => {
    const { container } = render(<PurchaseOrderStatusBadge status="draft" />)

    const badge = await screen.findByText('Sketch')
    expect(badge.parentElement).toHaveClass('bg-pink-100')
    expect(container.querySelector('svg.lucide-pen-line')).toBeInTheDocument()
    expect(screen.queryByText('Draft')).not.toBeInTheDocument()
  })

  it('marks an icon-less status with a dot of its color', async () => {
    const { container } = render(<PurchaseOrderStatusBadge status="approved" />)

    await screen.findByText('Green light')
    expect(container.querySelector('svg')).not.toBeInTheDocument()
    expect(container.querySelector('span.rounded-full')).toBeInTheDocument()
  })

  it('falls back to the raw value for a status the catalog lacks', async () => {
    render(<PurchaseOrderStatusBadge status="fulfilled" />)

    expect(await screen.findByText('fulfilled')).toBeInTheDocument()
  })
})

describe('StatusTabs', () => {
  it('lists "All" first, then the catalog statuses in catalog order with their names', async () => {
    render(<StatusTabs active="all" onSelect={vi.fn()} label="Filter by status" />)

    await screen.findByRole('tab', { name: 'Dropped' })
    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['All', 'Dropped', 'Sketch', 'Green light'])
  })

  it('selects by the enum value, whatever the name', async () => {
    const onSelect = vi.fn()
    render(<StatusTabs active="all" onSelect={onSelect} label="Filter by status" />)

    fireEvent.click(await screen.findByRole('tab', { name: 'Green light' }))

    expect(onSelect).toHaveBeenCalledWith('approved')
  })
})

describe('PurchaseOrderStatusOptions', () => {
  it('offers the allowed transitions with catalog names and icons, in catalog order', async () => {
    const onValueChange = vi.fn()
    const { container } = render(
      <div>
        <span id="target-label">Target</span>
        <PurchaseOrderStatusOptions labelledBy="target-label" options={['approved', 'draft']} value={null} onValueChange={onValueChange} />
      </div>,
    )

    const group = await screen.findByRole('radiogroup', { name: 'Target' })
    await waitFor(() =>
      expect(within(group).getAllByRole('radio').map((radio) => radio.closest('label')?.textContent)).toEqual(['Sketch', 'Green light']),
    )
    expect(container.querySelector('svg.lucide-pen-line')).toBeInTheDocument()

    fireEvent.click(within(group).getByRole('radio', { name: 'Green light' }))
    expect(onValueChange).toHaveBeenCalledWith('approved')
  })
})
