import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CommissionSettlementsTable } from '@/features/commission-settlements/commission-settlements-table'
import type { TableRowsAggregates } from '@/features/table/types'

const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))
vi.mock('@/features/activity-log/resource-activity-dialog', () => ({ ResourceActivityDialog: () => null }))

interface StubProps {
  domain: string
  forcedFilterModel?: Record<string, unknown>
  renderFooter?: (aggregates: TableRowsAggregates | undefined) => ReactNode
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, StubProps>(function TableViewStub(
    { domain, forcedFilterModel, renderFooter },
    ref,
  ) {
    useImperativeHandle(ref, () => ({ refresh: () => {}, setFilterModel: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`} data-forced={JSON.stringify(forcedFilterModel)}>
        {renderFooter?.({ taxable_amount: 1000, net_payable: 1020 })}
      </div>
    )
  }),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <CommissionSettlementsTable />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => canMock.mockReset().mockReturnValue(true))

describe('CommissionSettlementsTable (spec 0213 AC-021)', () => {
  it('forces the DRAFT status and selects the Drafts tab', () => {
    renderTable()

    const region = screen.getByRole('region', { name: 'table-commission-settlements' })
    expect(JSON.parse(region.getAttribute('data-forced') ?? '{}')).toEqual({
      status: { filterType: 'set', values: ['DRAFT'] },
    })
    expect(screen.getByRole('tab', { name: 'Drafts', selected: true })).toBeInTheDocument()
    for (const name of ['Issued', 'Document received', 'Approved', 'Cancelled', 'All']) {
      expect(screen.getByRole('tab', { name, selected: false })).toBeInTheDocument()
    }
  })

  it('renders only the footer totals present in the aggregates', () => {
    renderTable()

    const footer = screen.getByRole('group', { name: 'Totals of the current filter' })
    expect(footer).toHaveTextContent('Taxable amount')
    expect(footer).toHaveTextContent('Net payable')
    expect(footer).not.toHaveTextContent('VAT')
  })

  it('shows New adjustment only with the adjust permission', () => {
    renderTable()
    expect(screen.getByRole('button', { name: 'New adjustment' })).toBeInTheDocument()
  })

  it('hides New adjustment without the permission', () => {
    canMock.mockReturnValue(false)
    renderTable()
    expect(screen.queryByRole('button', { name: 'New adjustment' })).not.toBeInTheDocument()
  })
})
